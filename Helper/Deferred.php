<?php
namespace AristanderAi\Aai\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Deferred extends AbstractHelper
{
    private $callbacks = [];
    private $timeLimit = 3600;

    /** @var LoggerInterface */
    private $logger;

    /** @var StoreManagerInterface */
    private $storeManager;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;

        parent::__construct($context);
    }

    public function add($callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function execute(Response $response)
    {
        if (empty($this->callbacks)) {
            return $this;
        }

        $this->releaseRequest($response);

        $this->storeManager->setCurrentStore(0);

        set_time_limit($this->timeLimit);

        foreach ($this->callbacks as $callback) {
            try {
                call_user_func($callback);
            } catch (\Exception $e) {
                $this->logger->debug('Error while running deferred action: '
                    . $e->getMessage());
                $this->logger->critical($e);
            }
        }

        return $this;
    }

    private function releaseRequest(Response $response)
    {
        session_write_close();

        // check if fastcgi_finish_request is callable
        if (is_callable('fastcgi_finish_request')) {
            /*
             * This works in Nginx but the next approach not
             */
            fastcgi_finish_request();

            return;
        }

        header('Content-Length: ' . strlen($response->getBody()));
        header('Connection: close'); // not required - ?

        ob_flush();
        flush();
    }
}
