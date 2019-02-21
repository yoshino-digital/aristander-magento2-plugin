<?php
namespace AristanderAi\Aai\Service\PollApi;

use AristanderAi\Aai\Cron\ImportPrices\Exception;
use AristanderAi\Aai\Helper\PollApi\HttpClientCreator;
use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Observer\ProductSave as ProductSaveObserver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Data\CollectionFactory as ProductCollectionFactory;
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

    /** @var Data */
    private $helperData;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var ProductSaveObserver */
    private $productSaveObserver;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        HttpClientCreator $httpClientCreator,
        ProductRepository $productRepository,
        ResourceConnection $resource,
        Data $helperData,
        ProductCollectionFactory $productCollectionFactory,
        ProductSaveObserver $productSaveObserver
    ) {
        $this->logger = $logger;
        $this->httpClientCreator = $httpClientCreator;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
        $this->helperData = $helperData;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productSaveObserver = $productSaveObserver;
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
        
        //TODO: set price mode

        $this->logger->debug('Finished Aristander.ai price import');
    }

    /**
     * @param resource $stream
     * @throws \Exception
     */
    private function process($stream)
    {
        $productCollection = $this->productCollectionFactory->create();

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
                $product = $this->processProduct($data);
            } catch (NoSuchEntityException $e) {
                $this->logger->warning(__(
                    'Error at line %1: Product SKU \%2\' not found',
                    [$lineNo, $data['product_id']]
                ));
                continue;
            }

            if ($product) {
                $productCollection->addItem($product);
            }
        }

        if ($productCollection->count()) {
            $connection = $this->resource->getConnection();
            $connection->beginTransaction();
            try {
                $productCollection->walk([$this->productRepository, 'save']);
            } catch (Exception $e) {
                $connection->rollBack();
                throw $e;
            }

            $connection->commit();
        }
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
     * @param array $data
     * @return null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processProduct(array $data)
    {
        /** @var Product $product */
        $product = $this->productRepository->get($data['product_id']);
        
        //TODO: ignore bundles and grouped

        $price = $product->getData('price');
        if ($price == $data['price']) {
            // Price is the same
            return null;
        }

        $product->setData('price', $data['price']);

        return $product;
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
