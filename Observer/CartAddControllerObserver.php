<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Panto\Source\Model\CartAddEmitter;

/**
 * Luma catalog-add-to-cart POSTs to checkout/cart/add. Quote-item Magento events
 * are not reliable on every AJAX path; postdispatch always runs for that action.
 */
class CartAddControllerObserver implements ObserverInterface
{
    public function __construct(
        private readonly CartAddEmitter $cartAddEmitter,
        private readonly HttpRequest $request,
        private readonly CheckoutSession $checkoutSession,
        private readonly State $appState
    ) {
    }

    public function execute(Observer $observer): void
    {
        unset($observer);
        try {
            if ($this->appState->getAreaCode() === 'adminhtml') {
                $this->cartAddEmitter->markSkip(CartAddEmitter::SKIP_ADMIN, []);
                return;
            }
        } catch (\Throwable) {
        }

        $this->cartAddEmitter->emitFromQuoteItem($this->resolveAddedItem());
    }

    private function resolveAddedItem(): mixed
    {
        $productId = (int) $this->request->getParam('product');
        if ($productId <= 0) {
            return null;
        }
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (\Throwable) {
            return null;
        }
        if (!$quote || !method_exists($quote, 'getAllVisibleItems')) {
            return null;
        }
        $match = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if (is_object($item) && (int) $item->getProductId() === $productId) {
                $match = $item;
            }
        }
        return $match;
    }
}
