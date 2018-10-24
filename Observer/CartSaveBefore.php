<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\CartRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;

class CartSaveBefore implements ObserverInterface
{
    /** @var Data */
    protected $helperData;

    /** @var CartRecorder */
    protected $cartRecorder;

    public function __construct(
        Data $helperData,
        CartRecorder $cartRecorder
    ) {
        $this->helperData = $helperData;
        $this->cartRecorder = $cartRecorder;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('basket')) {
            return;
        }

        /** @var \Magento\Checkout\Model\Cart  $cart */
        $cart = $observer->getData('cart');

        /** @var Item $item */
        foreach ($cart->getQuote()->getItemsCollection() as $item) {
            if ($item->getParentItem()) {
                // Invisible item
                continue;
            }

            if (empty($item->getItemId())) {
                // Added item
                $this->cartRecorder->addItemChange($item, 'addition');
            } elseif ($item->isDeleted()) {
                // Removed item
                $this->cartRecorder->addItemChange($item, 'deletion');
            } elseif ($item->getOrigData(Item::KEY_QTY) != $item->getQty()) {
                // Qty change
                $this->cartRecorder->addItemChange(
                    $item,
                    $item->getQty() > $item->getOrigData(Item::KEY_QTY)
                        ? 'increase_quantity'
                        : 'decrease_quantity'
                );
            }
        }
    }
}