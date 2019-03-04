<?php
namespace AristanderAi\Aai\Service\PollApi;

use AristanderAi\Aai\Cron\ImportPrices\Exception;
use AristanderAi\Aai\Helper\PollApi\HttpClientCreator;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\Price;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Data\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Data\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class ImportPrices
{
    private $endPointUrl = 'https://api.aristander.ai/prices';

    /** @var array Expected column names */
    private $columnNames = [
        'product_id',
        'price',
    ];

    private $unsupportedProductTypes = [
        'bundle',
        'grouped',
    ];

    private $priceModeMap = [
        'aristander' => 'alternative',
        'default' => 'original',
    ];

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    private $logger;

    /** @var \Zend\Http\Client */
    private $httpClient;

    /** @var HttpClientCreator */
    private $httpClientCreator;

    /** @var ProductRepository */
    private $productRepository;

    /** @var ResourceConnection */
    private $resource;

    /** @var Configurable  */
    private $modelProductConfigurable;

    /** @var Data */
    private $helperData;

    /** @var Price */
    private $helperPrice;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var ProductCollection */
    private $productCollection;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        HttpClientCreator $httpClientCreator,
        ProductRepository $productRepository,
        ResourceConnection $resource,
        Configurable $modelProductConfigurable,
        Data $helperData,
        Price $helperPrice,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->logger = $logger;
        $this->httpClientCreator = $httpClientCreator;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
        $this->modelProductConfigurable = $modelProductConfigurable;
        $this->helperData = $helperData;
        $this->helperPrice = $helperPrice;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->debug('Starting Aristander.ai price import');

        try {
            $this->initHttpClient();
        } catch (HttpClientCreator\NotConfiguredException $e) {
            $this->logger->debug('Aristander.ai price import is not configured');
            return;
        }

        $this->httpClient->setRawBody(json_encode([
            'type' => 'aggregate',
            'format' => 'csv',
        ]));

        /** @var \Zend\Http\Response\Stream $response */
        $response = $this->httpClient->send();
        if (!$response->isOk()) {
            throw new Exception(__(
                'API error %1: %2',
                [$response->getStatusCode(), $response->getBody()])
            );
        }

        $this->process($response->getStream());

        // Set price mode
        $modeHeader = $response->getHeaders()->get('price_mode');
        if ($modeHeader) {
            $mode = $modeHeader->getFieldValue();
            if (isset($this->priceModeMap[$mode])) {
                $mode = $this->priceModeMap[$mode];
            }

            $this->logger->debug("Setting price mode to '{$mode}'");
            $this->helperPrice->setMode($mode);
        }

        $this->logger->debug('Finished Aristander.ai price import');
    }

    /**
     * @param resource $stream
     * @throws \Exception
     */
    private function process($stream)
    {
        $this->productCollection = $this->productCollectionFactory->create();

        rewind($stream);

        $columns = null;
        $columnIndexes = null;
        $lineNo = 0;
        while (!feof($stream)) {
            $lineNo++;
            $row = $this->readRow($stream);
            if (null === $row) {
                continue;
            }

            if (null === $columnIndexes) {
                // Parse header
                $columns = $row;
                try {
                    $columnIndexes = $this->mapColumns($columns);
                } catch (Exception $e) {
                    throw new Exception(__(
                        'Error at line %1: %2',
                        [$lineNo, $e->getMessage()]
                    ));
                }

                continue;
            }

            if (count($row) != count($columns)) {
                throw new Exception(__(
                    'Error at line %1: Invalid file format - expect %2 columns, found %3',
                    [$lineNo, count($columns), count($row)]
                ));
            }

            $data = [];
            foreach ($this->columnNames as $columnName) {
                $data[$columnName] = $row[$columnIndexes[$columnName]];
            }

            try {
                /** @var Product $product */
                $product = $this->productRepository->get($data['product_id']);
            } catch (NoSuchEntityException $e) {
                $this->logger->warning(__(
                    "Error at line %1: Product SKU '%2' not found",
                    [$lineNo, $data['product_id']]
                ));
                continue;
            }

            // Ignore bundles & grouped
            if (in_array($product->getTypeId(), $this->unsupportedProductTypes)) {
                $this->logger->warning(__(
                    "Warning at line %1: Product #%2 '%3' type is '%4'. This type is not supported.",
                    [
                        $lineNo,
                        $product->getId(),
                        $product->getId(),
                        $product->getTypeId(),
                    ]
                ));
                continue;
            }

            $price = $data['price'];
            $this->applyPriceToProduct($price, $product);

            if ('configurable' == $product->getTypeId()) {
                $childProducts = $this->modelProductConfigurable
                    ->getUsedProducts($product);
                foreach ($childProducts as $childProduct) {
                    $this->applyPriceToProduct($price, $childProduct);
                }
            }
        }

        if ($this->productCollection->count()) {
            $connection = $this->resource->getConnection();
            $connection->beginTransaction();
            try {
                $this->productCollection->walk([$this->productRepository, 'save']);
            } catch (\Exception $e) {
                $connection->rollBack();
                throw $e;
            }

            $connection->commit();
        }

        $this->productCollection = null;
    }

    private function readRow($stream)
    {
        $result = fgetcsv($stream);
        if (!is_array($result) || (1 == count($result) && null === $result[0])) {
            return null;
        }
        if ('' === $result[0]) {
            // Skip records with empty fields
            return null;
        }

        return $result;
    }

    /**
     * @param array $columns
     * @return array
     * @throws Exception
     */
    private function mapColumns(array $columns)
    {
        $result = [];

        if (count($columns) < count($this->columnNames)) {
            // Too little columns
            throw new Exception(__(
                'Invalid file format - expect at least %1 columns, found %2',
                [count($this->columnNames), count($columns)]
            ));
        }

        foreach ($this->columnNames as $columnName) {
            $index = array_search($columnName, $columns);
            if (false === $index) {
                throw new Exception(__(
                    'Invalid file format - column \'%1\' not found',
                    [$columnName]
                ));
            }

            $result[$columnName] = $index;
        }

        return $result;
    }

    /**
     * @param float $price
     * @param ProductInterface $product
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    private function applyPriceToProduct($price, ProductInterface $product)
    {
        /** @var Product $product */
        $oldPrice = $this->formatPrice(
            $this->helperPrice->getProductAlternativePrice($product)
        );
        $price = $this->formatPrice($price);

        if ($oldPrice !== $price) {
            if (null !== $price) {
                $this->logger->debug("Changing alternative price for product #{$product->getId()} '{$product->getSku()}' from {$oldPrice} to {$price}");
            } else {
                $this->logger->debug("Removing alternative price for product #{$product->getId()} '{$product->getSku()}'");
            }

            $this->helperPrice->setProductAlternativePrice($product, $price);
            $this->productCollection->addItem($product);
        }
    }

    /**
     * Converts price value to string and normalizes it for comparison
     *
     * @param float|string|null $price
     * @return string|null
     */
    private function formatPrice($price)
    {
        if (null === $price || '' === $price) {
            return null;
        }

        $price = (string) $price;
        if (false !== strpos($price, '.')) {
            $price = rtrim($price, '0');
            $price = rtrim($price, '.');
        }

        return $price;
    }

    /**
     * Initializes HTTP client object
     *
     * @throws HttpClientCreator\NotConfiguredException
     * @throws HttpClientCreator\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\ValidatorException
     */
    private function initHttpClient()
    {
        $this->httpClient = $this->httpClientCreator->create([
            'url' => $this->helperData->getConfigValue('api/import_prices')
                ?: $this->endPointUrl,
            'tmpStream' => true,
        ]);
    }
}
