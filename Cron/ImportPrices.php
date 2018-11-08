<?php
namespace AristanderAi\Aai\Cron;

use AristanderAi\Aai\Cron\ImportPrices\Exception;
use AristanderAi\Aai\Helper\ApiHttpClient;
use AristanderAi\Aai\Helper\ApiHttpClient\NotConfiguredException;
use AristanderAi\Aai\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\ResourceConnection;
use /** @noinspection PhpUndefinedClassInspection */
    \Psr\Log\LoggerInterface;

class ImportPrices
{
    protected $endPointUrl = 'https://api.aristander.ai/prices';

    /** @var array Expected column names */
    protected $columnNames = [
        'product_id',
        'price',
    ];

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface */
    protected $logger;

    /** @var \Zend\Http\Client */
    protected $httpClient;

    /** @var ApiHttpClient */
    protected $helperApiHttpClient;

    /** @var ProductRepository */
    protected $productRepository;

    /** @var ResourceConnection */
    protected $resource;

    /** @var Data */
    protected $helperData;

    public function __construct(
        /** @noinspection PhpUndefinedClassInspection */
        LoggerInterface $logger,
        ApiHttpClient $helperApiHttpClient,
        ProductRepository $productRepository,
        ResourceConnection $resource,
        Data $helperData
    ) {
        $this->logger = $logger;
        $this->helperApiHttpClient = $helperApiHttpClient;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
        $this->helperData = $helperData;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $this->initHttpClient();
        } catch (NotConfiguredException $e) {
            $this->logger->debug('Aristander.ai price import is not configured');
            return;
        }

        $this->logger->debug('Starting Aristander.ai price import');

        $this->httpClient->setRawBody(json_encode([
            'type' => 'aggregate',
            'format' => 'csv',
        ]));
        $this->httpClient->send();
        /** @var \Zend\Http\Response\Stream $response */
        $response = $this->httpClient->getResponse();
        if (!$response->isOk()) {
            throw new Exception("API error {$response->getStatusCode()}: {$response->getBody()}");
        }

        $this->process($response->getStream());


        // Test code for local file
        /*
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $rootPath = $directory->getRoot();
        $this->process(fopen($rootPath . '/development/price-extra-column.csv', 'r'));
        */

        $this->logger->debug("Finished Aristander.ai price import.");
    }

    /**
     * @param resource $stream
     * @throws Exception
     * @throws \Exception
     */
    protected function process($stream)
    {
        $connection = $this->resource->getConnection();

        $connection->beginTransaction();
        try {
            rewind($stream);

            $columns = null;
            $columnIndexes = null;
            $lineNo = 0;
            while (!feof($stream)) {
                $lineNo++;
                $row = fgetcsv($stream);
                if (!is_array($row) || (1 == count($row) && is_null($row[0]))) {
                    // Empty or bad record
                    continue;
                }
                if ('' === $row[0]) {
                    // Skip records with empty fields
                    continue;
                }

                if (is_null($columnIndexes)) {
                    // Parse header
                    $columns = $row;
                    $columnIndexes = [];

                    if (count($columns) < count($this->columnNames)) {
                        // Too little columns
                        throw new Exception("Error at CSV line {$lineNo}: Invalid file format - expect at least "
                            . count($this->columnNames) . " columns, found " . count($row));
                    }

                    foreach ($this->columnNames as $columnName) {
                        $index = array_search($columnName, $columns);
                        if (FALSE === $index) {
                            throw new Exception("Error at CSV line {$lineNo}: Invalid file format - column '{$columnName}' not found");
                        }

                        $columnIndexes[$columnName] = $index;
                    }

                    continue;
                }

                if (count($row) != count($columns)) {
                    throw new Exception("Error at CSV line {$lineNo}: Invalid file format - expect "
                        . count($columns) . " columns, found "
                        . count($row));
                }

                $data = [];
                foreach ($this->columnNames as $columnName) {
                    $data[$columnName] = $row[$columnIndexes[$columnName]];
                }

                /** @var Product $product */
                $product = $this->productRepository->getById($data['product_id']);

                if (!$product || !$product->getId()) {
                    // ID not found
                    $this->logger->warning("Error at CSV line {$lineNo}: Product ID '{$data['product_id']}' not found");
                    continue;
                }

                if ($product->getPrice() == $data['price']) {
                    // Price is the same
                    continue;
                }

                $product->setPrice($data['price']);

                $this->productRepository->save($product);
            }

        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $connection->commit();
    }

    /**
     * Initializes HTTP client object
     *
     * @throws ApiHttpClient\NotConfiguredException
     * @throws ApiHttpClient\Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function initHttpClient()
    {
        $this->httpClient = $this->helperApiHttpClient->init([
            'url' => $this->helperData->getConfigValue('api/import_prices')
                ?? $this->endPointUrl,
            'tmpStream' => true,
        ]);
    }
}