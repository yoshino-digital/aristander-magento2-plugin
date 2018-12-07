<?php
namespace AristanderAi\Aai\Observer;

use AristanderAi\Aai\Helper\Data;
use AristanderAi\Aai\Service\CartRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;

class CartSave implements ObserverInterface
{
    /** @var Data */
    private $helperData;

    /** @var CartRecorder */
    private $cartRecorder;

    public function __construct(
        Data $helperData,
        CartRecorder $cartRecorder
    ) {
        $this->helperData = $helperData;
        $this->cartRecorder = $cartRecorder;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEventTypeEnabled('basket')) {
            return;
        }

        switch ($observer->getEvent()->getName()) {
            case 'checkout_cart_save_before':
                $this->beforeSave($observer);
                break;

            case 'checkout_cart_save_after':
                $this->afterSave();
                break;
        }
    }

    /**
     * @param Observer $observer
     */
    private function beforeSave(Observer $observer)
    {
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

    /**
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function afterSave()
    {
        $this->cartRecorder->saveEvents();
    }
}
