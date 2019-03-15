<?php
namespace AristanderAi\Aai\Plugin;

use AristanderAi\Aai\Helper\Deferred;
use Magento\Framework\HTTP\PhpEnvironment\Response;

class ResponsePlugin
{
    /** @var Deferred */
    private $helperDeferred;

    public function __construct(Deferred $helperDeferred)
    {
        $this->helperDeferred = $helperDeferred;
    }

    /**
     * @param Response $subject
     */
    public function afterSendResponse(Response $subject)
    {
        $this->helperDeferred->execute($subject);
    }
}
