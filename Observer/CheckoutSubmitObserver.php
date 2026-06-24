<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\Data\OrderInterface;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\CheckoutIdentityRepository;
use Panto\Source\Model\CustomerProfileBuilder;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\HashedPiiEnricher;
use Panto\Source\Model\OrderEnrichment;
use Panto\Source\Model\OrderLineSerializer;

class CheckoutSubmitObserver implements ObserverInterface
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly OrderLineSerializer $orderLineSerializer,
        private readonly HashedPiiEnricher $hashedPiiEnricher,
        private readonly CustomerProfileBuilder $customerProfileBuilder,
        private readonly CheckoutIdentityRepository $checkoutIdentity,
        private readonly OrderEnrichment $orderEnrichment
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getEntityId()) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $this->checkoutIdentity->copySessionToOrder($orderId);

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
            'occurrence' => 'order_created',
            'is_new_customer' => $this->orderEnrichment->isNewCustomer($order),
            'coupon_codes' => $this->orderEnrichment->getCouponCodes($order),
            'context' => StorefrontContext::fromRequest($this->request, 'checkout', 'Order placed'),
            'customer_profile' => $this->customerProfileBuilder->profileFromOrder($order),
        ];
        $data = $this->hashedPiiEnricher->enrich($order, $data, (int) $order->getStoreId());
        $data = $this->checkoutIdentity->resolveIdentityFromOrder($data, $orderId);

        $this->dispatcher->dispatch('magento_checkout_order_placed', $data);
    }
}
