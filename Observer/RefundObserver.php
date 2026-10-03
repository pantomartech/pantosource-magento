<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;

/**
 * Fires after a credit memo (refund) is saved.
 * Emits magento_refund_created with order and refund details.
 */
class RefundObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly CustomerProfileBuilder $customerProfileBuilder,
        private readonly HashedPiiEnricher $hashedPiiEnricher,
        private readonly CheckoutIdentityRepository $checkoutIdentity
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo) {
            return;
        }

        $order = $creditmemo->getOrder();
        $orderId = $order ? (int) $order->getId() : 0;
        if ($orderId <= 0 || !$this->checkoutIdentity->isOnlineStoreOrder($orderId)) {
            return;
        }
        $currency = $order ? (string) $order->getOrderCurrencyCode() : '';

        $items = [];
        foreach ($creditmemo->getAllItems() as $item) {
            if ((float) $item->getQty() <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => (int) $item->getProductId(),
                'title'      => (string) $item->getName(),
                'sku'        => (string) $item->getSku(),
                'quantity'   => (float) $item->getQty(),
                'unit_price' => (float) $item->getPrice(),
                'total'      => (float) $item->getRowTotal(),
            ];
        }

        $data = [
            'order_id'      => $orderId,
            'creditmemo_id' => (int) $creditmemo->getId(),
            'refund_amount' => (string) abs((float) $creditmemo->getGrandTotal()),
            'currency'      => $currency,
            'items'         => $items,
            'context'       => StorefrontContext::fromRequest($this->request, '', ''),
            'sales_channel' => $this->checkoutIdentity->getSalesChannel($orderId) ?? '',
        ];
        if ($order) {
            $data['customer_id'] = (int) $order->getCustomerId();
            $data['billing_email'] = (string) $order->getCustomerEmail();
            $data['customer_profile'] = $this->customerProfileBuilder->profileFromOrder($order);
            $data = $this->hashedPiiEnricher->enrich($order, $data, (int) $order->getStoreId());
        }
        $this->dispatcher->dispatch('magento_refund_created', $data);
    }
}
