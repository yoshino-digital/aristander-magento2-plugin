<?php

namespace AristanderAi\Aai\Controller\Api;

use AristanderAi\Aai\Controller\Api;

class AuthError extends Api
{
    /**
     * @return\Magento\Framework\Controller\Result\Json
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        if ($request->isForwarded()) {
            throw new \Magento\Framework\Exception\NotFoundException(__('Not found'));
        }

        return $this->generateResponse();
    }
}
