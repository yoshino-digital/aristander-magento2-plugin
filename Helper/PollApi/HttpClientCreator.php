<?php
namespace AristanderAi\Aai\Helper\PollApi;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Helper\PollApi\HttpClientCreator\Exception;
use AristanderAi\Aai\Helper\PollApi\HttpClientCreator\NotConfiguredException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Zend\Http\Client as HttpClient;
use Zend\Http\Exception\InvalidArgumentException;
use Zend\Http\Request;

class HttpClientCreator extends AbstractHelper
{
    /** @var HttpClient */
    private $httpClient;

    /** @var Data */
    private $helperData;

    /** @var Filesystem */
    private $filesystem;

    private $commonHttpClientOptions = [
        'maxredirects' => 0,
        'timeout' => 30,
    ];

    public function __construct(
        Context $context,
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        HttpClient $httpClient,
        Data $helperData,
        Filesystem $filesystem
    ) {
        $this->httpClient = $httpClient;
        $this->helperData = $helperData;
        $this->filesystem = $filesystem;

        parent::__construct($context);
    }

    /**
     * @param array $options Options:
     *  * url: request URL
     *  * tmpStream: bool specifies if response should be written to temporary stream file
     * @return HttpClient
     * @throws NotConfiguredException
     * @throws Exception
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\ValidatorException
     */
    public function create(array $options = [])
    {
        $apiKey = $this->helperData->getConfigValue('general/api_key');
        if (empty($apiKey)) {
            throw new NotConfiguredException(__('Poll API key not configured'));
        }

        try {
            $this->httpClient->reset();

            $httpClientOptions = $this->commonHttpClientOptions;

            // Optional stuff

            if (isset($options['url'])) {
                $this->httpClient->setUri($options['url']);
            }
            if (isset($options['tmpStream']) && $options['tmpStream']) {
                /** @var \Magento\Framework\Filesystem\Directory\Write $directory */
                $directory = $this->filesystem->getDirectoryWrite(
                    DirectoryList::TMP
                );
                $directory->create();

                $httpClientOptions['streamtmpdir'] = $directory->getAbsolutePath();
                $this->httpClient->setStream(true);
            }

            // Common stuff

            $this->httpClient->setMethod(Request::METHOD_POST);
            $this->httpClient->setOptions($httpClientOptions);
            $this->httpClient->setEncType('application/json');
            /** @noinspection MissedFieldInspection */
            $this->httpClient->setHeaders([
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':')
            ]);
        } catch (InvalidArgumentException $e) {
            throw new Exception(__(
                'HTTP client configuration error: %1',
                [$e->getMessage()]
            ));
        }

        return $this->httpClient;
    }
}
