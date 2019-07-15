<?php
namespace AristanderAi\Aai\Plugin;

class CsrfValidatorPlugin
{
    /**
     * @param \Magento\Framework\App\Request\CsrfValidator $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        /** @noinspection PhpUndefinedMethodInspection */
        if ('aristander-ai' == $request->getModuleName()
            && 'api' == $request->getControllerName()
        ) {
            return; // Skip CSRF check
        }
        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}
