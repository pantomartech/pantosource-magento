<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panto\Source\Model\CartAddEmitter;

/**
 * sales_quote_item_save_commit_after: emits magento_add_to_cart for a new cart line that the
 * add observers saw before it was saved, so the event carries its quote item and cart ids.
 */
class CartAddSaveCommitObserver implements ObserverInterface
{
    public function __construct(
        private readonly CartAddEmitter $cartAddEmitter
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->cartAddEmitter->emitPendingAfterSave($observer->getEvent()->getItem());
    }
}
