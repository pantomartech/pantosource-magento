<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Tracker;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\AttributionGuardrails;
use Panto\Source\Model\AttributionWindowCapture;
use Panto\Source\Model\EventDispatcher;
use Panto\Source\Model\EventRegistry;
use Panto\Source\Model\FrontendRateLimiter;
use Panto\Source\Model\QueueBroker;

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
        private readonly FrontendRateLimiter $rateLimiter
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
            'magento_add_to_cart',
        ];
        if (!in_array($eventName, $allowed, true)) {
            return $result->setData(['ok' => false, 'error' => 'unsupported_event'])->setHttpResponseCode(400);
        }

        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        if (isset($body['context']) && is_array($body['context'])) {
            $data['context'] = $this->mergeContext($body['context'], $data['context'] ?? []);
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

        $touchParams = array_merge($incoming, ['referrer' => $referrer]);
        if (!AttributionGuardrails::shouldUpdateLastTouch($touchParams, $shopHost)) {
            $window = $this->attributionWindow->readCurrent($storeId);
        } else {
            $window = $this->attributionWindow->capture($storeId, $incoming, $shopHost, $landingUrl, $referrer);
        }

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
}
