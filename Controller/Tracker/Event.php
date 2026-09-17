<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Tracker;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Helper\QuoteCommerceSnapshot;
use Panto\Source\Model\AttributionWindowCapture;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\EventRegistry;
use Panto\Source\Model\FrontendRateLimiter;
use Panto\Source\Model\QueueBroker;
use Panto\Source\Observer\PaymentInfoObserver;

class Event extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKey $formKey,
        private readonly EventDispatcher $dispatcher,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly AttributionWindowCapture $attributionWindow,
        private readonly FrontendRateLimiter $rateLimiter,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Skip framework CSRF (expects form_key in POST fields). This action accepts JSON
     * with form_key in the body; execute() validates it with hash_equals.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        if (!$this->config->isEnabled() || !$this->config->isFrontendTrackerEnabled()) {
            return $result->setData(['ok' => false, 'error' => 'disabled'])->setHttpResponseCode(503);
        }

        $raw = (string) $this->getRequest()->getContent();
        if (strlen($raw) > QueueBroker::MAX_BYTES) {
            return $result->setData(['ok' => false, 'error' => 'payload_too_large'])->setHttpResponseCode(413);
        }

        $clientKey = (string) ($this->getRequest()->getClientIp() ?: 'unknown');
        if (!$this->rateLimiter->isAllowed($clientKey)) {
            return $result->setData(['ok' => false, 'error' => 'rate_limited'])->setHttpResponseCode(429);
        }

        $body = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($body)) {
            return $result->setData(['ok' => false, 'error' => 'invalid_json'])->setHttpResponseCode(400);
        }

        $formKey = isset($body['form_key']) ? (string) $body['form_key'] : '';
        if ($formKey === '' || !hash_equals($this->formKey->getFormKey(), $formKey)) {
            return $result->setData(['ok' => false, 'error' => 'csrf'])->setHttpResponseCode(403);
        }

        $eventName = isset($body['eventName']) ? (string) $body['eventName'] : '';
        if ($eventName === '') {
            return $result->setData(['ok' => false, 'error' => 'missing_event'])->setHttpResponseCode(400);
        }

        $allowed = [
            'pageview',
            'product_viewed',
            'product_list_viewed',
            'search_performed',
            'checkout_initiated',
            'add_payment_info',
            'magento_customer_identified',
        ];
        if (!in_array($eventName, $allowed, true)) {
            return $result->setData(['ok' => false, 'error' => 'unsupported_event'])->setHttpResponseCode(400);
        }

        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        if (isset($body['context']) && is_array($body['context'])) {
            $data['context'] = $this->mergeContext($body['context'], $data['context'] ?? []);
        }

        if ($eventName === 'magento_customer_identified') {
            if (!$this->customerSession->isLoggedIn()) {
                return $result->setData(['ok' => false, 'error' => 'not_logged_in'])->setHttpResponseCode(401);
            }
            $data['customer_id'] = (int) $this->customerSession->getCustomerId();
            $email = (string) $this->customerSession->getCustomer()->getEmail();
            if ($email !== '') {
                $data['email'] = $email;
            }
        }

        if ($eventName === 'add_payment_info') {
            $this->checkoutSession->setData(PaymentInfoObserver::SESSION_KEY, 1);
            $data = $this->enrichPaymentInfoFromQuote($data);
            $data = $this->attachQuoteEmail($data);
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($storeId);
        $shopHost = (string) (parse_url((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB), PHP_URL_HOST) ?: $store->getCode());

        $incoming = [];
        if ($this->config->isAttributionEnabled($storeId)) {
            foreach (AttributionWindowCapture::ATTRIBUTION_KEYS as $k) {
                if (isset($body[$k]) && is_string($body[$k]) && $body[$k] !== '') {
                    $incoming[$k] = $body[$k];
                    $data[$k] = $body[$k];
                }
            }
        }

        $landingUrl = '';
        $referrer = '';
        if (isset($data['context']['page']['url']) && is_string($data['context']['page']['url'])) {
            $landingUrl = $data['context']['page']['url'];
        }
        if (isset($data['context']['page']['referrer']) && is_string($data['context']['page']['referrer'])) {
            $referrer = $data['context']['page']['referrer'];
        }

        $window = $this->attributionWindow->capture($storeId, $incoming, $shopHost, $landingUrl, $referrer);

        if (!isset($data['context']) || !is_array($data['context'])) {
            $data['context'] = [];
        }
        $data['context']['attribution'] = [
            'first_touch' => $window['first_touch'],
            'last_touch' => $window['last_touch'],
            'meta' => $window['meta'],
        ];

        $missing = EventRegistry::getMissingRequired($eventName, $data);
        if ($missing !== []) {
            return $result->setData(['ok' => false, 'error' => 'validation_failed', 'missing' => $missing])
                ->setHttpResponseCode(422);
        }

        $this->dispatcher->dispatch($eventName, $data, '', true, $storeId);
        if ($eventName === 'add_payment_info') {
            $this->maybeDispatchCheckoutIdentify($data, $storeId);
        }
        return $result->setData(['ok' => true]);
    }

    /**
     * @param array<string, mixed> $fromBody
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function mergeContext(array $fromBody, array $existing): array
    {
        $attribution = [];
        if (isset($fromBody['marketing']['attribution']) && is_array($fromBody['marketing']['attribution'])) {
            $attribution = $fromBody['marketing']['attribution'];
        }
        return array_replace_recursive($existing, $fromBody, [
            'attribution' => array_replace_recursive(
                $existing['attribution'] ?? [],
                $attribution
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function enrichPaymentInfoFromQuote(array $data): array
    {
        $needsItems = empty($data['items']) || !is_array($data['items']);
        $needsTotal = !isset($data['cart_total']) || $data['cart_total'] === '';
        $needsCurrency = !isset($data['currency']) || $data['currency'] === '';
        $needsTotals = empty($data['order_totals']) || !is_array($data['order_totals']);
        if (!$needsItems && !$needsTotal && !$needsCurrency && !$needsTotals) {
            return $data;
        }
        try {
            $snapshot = QuoteCommerceSnapshot::fromQuote($this->checkoutSession->getQuote());
        } catch (\Throwable) {
            return $data;
        }
        if ($needsItems && $snapshot['items'] !== []) {
            $data['items'] = $snapshot['items'];
        }
        if ($needsTotal && $snapshot['cart_total'] !== '') {
            $data['cart_total'] = $snapshot['cart_total'];
        }
        if ($needsCurrency && $snapshot['currency'] !== '') {
            $data['currency'] = $snapshot['currency'];
        }
        if ($needsTotals) {
            $data['order_totals'] = $snapshot['order_totals'];
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attachQuoteEmail(array $data): array
    {
        if (!$this->emptyString($data['billing_email'] ?? null) || !$this->emptyString($data['email'] ?? null)) {
            return $data;
        }
        try {
            $quote = $this->checkoutSession->getQuote();
            $billing = $quote && method_exists($quote, 'getBillingAddress') ? $quote->getBillingAddress() : null;
            $email = '';
            if ($billing && method_exists($billing, 'getEmail')) {
                $email = trim((string) $billing->getEmail());
            }
            if ($email === '' && $quote && method_exists($quote, 'getCustomerEmail')) {
                $email = trim((string) $quote->getCustomerEmail());
            }
            if ($email !== '') {
                $data['billing_email'] = $email;
            }
        } catch (\Throwable) {
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function maybeDispatchCheckoutIdentify(array $data, int $storeId): void
    {
        $email = trim((string) ($data['billing_email'] ?? $data['email'] ?? ''));
        if ($email === '') {
            return;
        }
        if ($this->checkoutSession->getData(PaymentInfoObserver::IDENTIFY_SESSION_KEY)) {
            return;
        }
        $this->checkoutSession->setData(PaymentInfoObserver::IDENTIFY_SESSION_KEY, 1);
        $identify = [
            'email' => $email,
            'billing_email' => $email,
            'identity_link' => true,
            'identity_trigger' => 'checkout_email',
        ];
        if (!empty($data['customer_profile']) && is_array($data['customer_profile'])) {
            $identify['customer_profile'] = $data['customer_profile'];
        }
        $this->dispatcher->dispatch('magento_customer_identified', $identify, '', true, $storeId);
    }

    private function emptyString(mixed $value): bool
    {
        return !is_string($value) || trim($value) === '';
    }
}
