<?php
namespace AristanderAi\Aai\Service\PollApi;

use AristanderAi\Aai\Service\PollApi\ImportPrices\Exception;
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

    private $alternativePriceProductSet;

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

        $headers = $response->getHeaders();

        // Decompress gzip compressed CSV if needed
        $isCompressed = $headers->has('content-type')
            && 'application/gzip' == $headers->get('content-type')->getFieldValue();
        $stream = $isCompressed
            ? fopen(
                'compress.zlib://' . $response->getStreamName(),
                'r'
            )
            : $response->getStream();

        $this->process($stream);

        if ($isCompressed) {
            fclose($stream);
        }

        // Set model params
        if ($headers->has('model_params')) {
            $value = $headers->get('model_params')->getFieldValue();
            $this->logger->debug("Setting model params to '{$value}'");
            $this->helperPrice->setModelParams($value);
        }

        $this->logger->debug('Finished Aristander.ai price import');
    }

    /**
     * @param resource $stream
     * @throws \Exception
     */
    private function process($stream)
    {
        // Get existing alternative prices
        $this->alternativePriceProductSet = array_flip(
            $this->getAlternativePriceProductIds()
        );

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

            if ($this->productCollection->getItemById($product->getId())) {
                $this->logger->warning(__("Warning at line %1: Product #%2 '%3' is already processed",
                    [
                        $lineNo,
                        $product->getId(),
                        $product->getId(),
                    ]
                ));
                continue;
            }

            $price = $data['price'];
            $this->applyPriceToProduct($price, $product);
        }

        if ($this->alternativePriceProductSet) {
            $this->logger->debug('Removing alternative prices for products not listed in import');
            foreach (array_keys($this->alternativePriceProductSet) as $id) {
                // Was already handled (as a child product)
                if ($this->productCollection->getItemById($id)) {
                    continue;
                }
                // Load product
                /** @var Product $product */
                $product = $this->productRepository->getById($id);
                if (!$product->getId()) {
                    // ID not found - this normally shouldn't happen
                    continue;
                }

                $this->removeAlternativePriceFromProduct($product);
            }
        }

        // Free memory
        $this->alternativePriceProductSet = null;

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

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Initializes HTTP client object
     *
     * @throws HttpClientCreator\NotConfiguredException
     * @throws HttpClientCreator\Exception
     */
    private function initHttpClient()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->httpClient = $this->httpClientCreator->create([
            'url' => $this->helperData->getConfigValue('api/import_prices')
                ?: $this->endPointUrl,
            'tmpStream' => true,
        ]);
    }

    /**
     * Returns array of product IDs with assigned alternative prices
     */
    private function getAlternativePriceProductIds()
    {
        $db = $this->resource->getConnection();
        $select = $db->select()
            ->from($this->resource->getTableName(
                'catalog_product_entity_tier_price'
            ))
            ->reset('columns')
            ->columns(array('entity_id'))
            ->where(
                'customer_group_id = ?',
                $this->helperPrice->getCustomerGroupId()
            )
            ->where('website_id = ?', 0);

        return $db->fetchCol($select);
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param float $price
     * @param ProductInterface $product
     */
    private function applyPriceToProduct($price, ProductInterface $product)
    {
        /** @var Product $product */

        unset($this->alternativePriceProductSet[$product->getId()]);

        /** @noinspection PhpUnhandledExceptionInspection */
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

            /** @noinspection PhpUnhandledExceptionInspection */
            $this->helperPrice->setProductAlternativePrice($product, $price);
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->productCollection->addItem($product);
        }

        if ('configurable' == $product->getTypeId()) {
            if (null !== $price) {
                $this->logger->debug("Changing alternative prices for child products of configurable product #{$product->getId()} '{$product->getSku()}' to {$price}");
            } else {
                $this->logger->debug("Removing alternative prices for child products of configurable product #{$product->getId()} '{$product->getSku()}'");
            }

            $childProducts = $this->modelProductConfigurable
                ->getUsedProducts($product);
            foreach ($childProducts as $childProduct) {
                $this->applyPriceToProduct($price, $childProduct);
            }
        }
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param ProductInterface $product
     */
    private function removeAlternativePriceFromProduct(ProductInterface $product)
    {
        /** @var Product $product */

        $this->logger->debug("Removing alternative price for product #{$product->getId()} '{$product->getSku()}'");

        $this->helperPrice->setProductAlternativePrice($product, null);
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->productCollection->addItem($product);

        if ('configurable' == $product->getTypeId()) {
            $this->logger->debug("Removing alternative prices for child products of configurable product #{$product->getId()} '{$product->getSku()}'");
            $childProducts = $this->modelProductConfigurable
                ->getUsedProducts(null, $product);
            /** @var Product $childProduct */
            foreach ($childProducts as $childProduct) {
                $this->removeAlternativePriceFromProduct($childProduct);
            }
        }
    }
}
