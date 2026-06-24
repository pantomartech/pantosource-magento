<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\ConversionDedupeRepository;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;
use Panto\Source\Model\OrderEnrichment;
use Panto\Source\Model\OrderLineSerializer;

class CheckoutSuccessObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderLineSerializer $orderLineSerializer,
        private readonly ConversionDedupeRepository $dedupeRepository,
        private readonly HashedPiiEnricher $hashedPiiEnricher,
        private readonly CustomerProfileBuilder $customerProfileBuilder,
        private readonly CheckoutIdentityRepository $checkoutIdentity,
        private readonly OrderEnrichment $orderEnrichment
    ) {
    }

    public function execute(Observer $observer): void
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        $orderId = $this->resolveOrderId($orderIds);
        if ($orderId <= 0) {
            return;
        }

        if ($this->dedupeRepository->isPurchaseEmitted($orderId)) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable) {
            return;
        }

        $data = [
            'order_id' => $orderId,
            'order_increment_id' => (string) $order->getIncrementId(),
            'status' => (string) $order->getStatus(),
            'total' => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'customer_id' => (int) $order->getCustomerId(),
            'items' => $this->orderLineSerializer->getItems($order),
            'order_totals' => $this->orderLineSerializer->orderTotals($order),
            'billing_email' => (string) $order->getCustomerEmail(),
            'occurrence' => 'first',
            'is_new_customer' => $this->orderEnrichment->isNewCustomer($order),
            'coupon_codes' => $this->orderEnrichment->getCouponCodes($order),
            'context' => StorefrontContext::fromRequest($this->request, 'checkout', 'Thank you'),
            'customer_profile' => $this->customerProfileBuilder->profileFromOrder($order),
        ];
        $data = $this->hashedPiiEnricher->enrich($order, $data, (int) $order->getStoreId());
        $data = $this->checkoutIdentity->resolveIdentityFromOrder($data, $orderId);

        $ok = $this->dispatcher->dispatch('magento_purchase_completed', $data, 'conversion');
        if ($ok) {
            $this->dedupeRepository->markPurchaseEmitted($orderId);
        }
    }

    /**
     * @param mixed $orderIds
     */
    private function resolveOrderId(mixed $orderIds): int
    {
        if (is_array($orderIds) && $orderIds !== []) {
            $vals = array_values($orderIds);
            return (int) ($vals[0] ?? 0);
        }
        return 0;
    }
}
