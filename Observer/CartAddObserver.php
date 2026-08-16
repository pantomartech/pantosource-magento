<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panto\Source\Model\CartAddEmitter;

class CartAddObserver implements ObserverInterface
{
    public function __construct(
        private readonly CartAddEmitter $cartAddEmitter,
        private readonly State $appState
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            if ($this->appState->getAreaCode() === 'adminhtml') {
                $this->cartAddEmitter->markSkip(CartAddEmitter::SKIP_ADMIN, []);
                return;
            }
        } catch (\Throwable) {
        }

        $this->cartAddEmitter->emitFromQuoteItem($this->resolveQuoteItem($observer));
    }

    private function resolveQuoteItem(Observer $observer): mixed
    {
        $event = $observer->getEvent();
        $item = $event->getQuoteItem();
        if (is_object($item)) {
            return $item;
        }
        $items = $event->getItems();
        if (!is_array($items) || $items === []) {
            return null;
        }
        $last = $items[array_key_last($items)];
        return is_object($last) ? $last : null;
    }
}
