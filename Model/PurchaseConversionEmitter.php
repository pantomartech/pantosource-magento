<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Panto\Source\Helper\CheckoutInitSessionKey;
use Panto\Source\Helper\StorefrontContext;
use Psr\Log\LoggerInterface;

/**
 * Shared magento_purchase_completed conversion emit with purchase-first dedupe.
 */
class PurchaseConversionEmitter
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly OrderLineSerializer $orderLineSerializer,
        private readonly ConversionDedupeRepository $dedupeRepository,
        private readonly HashedPiiEnricher $hashedPiiEnricher,
        private readonly CustomerProfileBuilder $customerProfileBuilder,
        private readonly CheckoutIdentityRepository $checkoutIdentity,
        private readonly OrderEnrichment $orderEnrichment,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function emit(OrderInterface $order, string $occurrence, string $pageTitle): bool
    {
        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0 || $this->dedupeRepository->isPurchaseEmitted($orderId)) {
            return false;
        }
        if (!$this->checkoutIdentity->isOnlineStoreOrder($orderId)) {
            $this->logger->debug('PantoSource skip purchase: not online store', [
                'order_id' => $orderId,
            ]);
            return false;
        }

        $salesChannel = $this->checkoutIdentity->getSalesChannel($orderId) ?? '';

        $paymentStatus = PaymentStatusNormalizer::fromOrder($order);
        $data = [
            'order_id' => $orderId,
            'order_increment_id' => (string) $order->getIncrementId(),
            'status' => (string) $order->getStatus(),
            'payment_status' => $paymentStatus,
            'total' => (string) $order->getGrandTotal(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'customer_id' => (int) $order->getCustomerId(),
            'items' => $this->orderLineSerializer->getItems($order),
            'order_totals' => $this->orderLineSerializer->orderTotals($order),
            'billing_email' => (string) $order->getCustomerEmail(),
            'occurrence' => $occurrence,
            'is_new_customer' => $this->orderEnrichment->isNewCustomer($order),
            'coupon_codes' => $this->orderEnrichment->getCouponCodes($order),
            'context' => StorefrontContext::withCheckoutSuccessPage(
                StorefrontContext::fromRequest($this->request, 'checkout', $pageTitle),
                (string) $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true])
            ),
            'customer_profile' => $this->customerProfileBuilder->profileFromOrder($order),
            'sales_channel' => $salesChannel,
        ];
        $data = $this->hashedPiiEnricher->enrich($order, $data, (int) $order->getStoreId());
        $data = $this->checkoutIdentity->resolveIdentityFromOrder($data, $orderId);

        $ok = $this->dispatcher->dispatch('magento_purchase_completed', $data, 'track');
        if ($ok) {
            $this->dedupeRepository->markPurchaseEmitted($orderId);
            // The purchase carries its status, so a later change event for the same status is redundant.
            $this->dedupeRepository->markEmitted($orderId, $paymentStatus);
            $quoteId = (int) $order->getQuoteId();
            if ($quoteId > 0) {
                $this->checkoutSession->unsData(CheckoutInitSessionKey::forQuote($quoteId));
            }
        }
        return $ok;
    }
}
