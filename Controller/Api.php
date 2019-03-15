<?php

namespace AristanderAi\Aai\Controller;

use AristanderAi\Aai\Helper\PushApi;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

abstract class Api extends Action
{
    protected $errors = [];
    protected $data;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PushApi */
    private $helperPushApi;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PushApi $helperPushApi
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helperPushApi = $helperPushApi;

        return parent::__construct($context);
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authenticate()
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();

        $token = $request->getServer('PHP_AUTH_USER');
        if (empty($token)) {
            $this->triggerAuthError('no-auth');
            return false;
        } elseif ($token != $this->helperPushApi->getAccessToken()) {
            $this->triggerAuthError('invalid-auth');
            return false;
        }

        return true;
    }

    public function generateResponse()
    {
        $result = $this->resultJsonFactory->create();

        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();

        $error = $this->getRequest()->getParam('error');
        if (is_array($error)) {
            $this->errors[] = $error;
        }

        if (isset($this->errors[0]['status'])) {
            $response->setHttpResponseCode($this->errors[0]['status']);
        }

        $resultData = new \StdClass;
        if (!empty($this->errors)) {
            $resultData->errors = $this->errors;
        }
        if (null !== $this->data) {
            $resultData->data = $this->data;
        }

        $result->setData($resultData);

        return $result;
    }

    private function triggerAuthError($code)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();

        $authErrorMessages = [
            'no-auth' => 'Authentication required',
            'invalid-auth' => 'Invalid access token',
        ];

        $response->setHeader(
            'WWW-Authenticate',
            'Basic realm="Aristander.ai API"'
        );

        $error = [
            'status' => 401,
            'code' => $code,
        ];
        if (isset($authErrorMessages[$code])) {
            $error['title'] = $authErrorMessages[$code];
        }

        $this->_forward('authError');

        $request->setParam('error', $error);
    }

    public function addError(array $error)
    {
        $this->errors[] = $error;
    }

    public function clearErrors()
    {
        $this->errors = [];
    }
}
