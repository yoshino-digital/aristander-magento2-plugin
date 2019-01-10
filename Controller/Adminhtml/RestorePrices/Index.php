<?php
namespace AristanderAi\Aai\Controller\Adminhtml\RestorePrices;

use AristanderAi\Aai\Service\PriceRestoration;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Index extends \Magento\Backend\App\Action
{
    protected $_publicActions = ['index'];

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PriceRestoration */
    private $priceRestoration;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PriceRestoration $priceRestoration
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->priceRestoration = $priceRestoration;

        return parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $this->priceRestoration->execute([
            'maxItemCount' => 100,
        ]);

        $continue = $this->priceRestoration->processedCount < $this->priceRestoration->totalCount;
        $this->priceRestoration->setStatus($continue
            ? 'progress'
            : null
        );
        $result->setData(compact('continue'));

        return $result;
    }
}