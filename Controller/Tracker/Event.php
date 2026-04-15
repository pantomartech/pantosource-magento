<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Tracker;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Api\ConfigInterface;
use Panto\Source\Model\EventDispatcher;

class Event extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKey $formKey,
        private readonly EventDispatcher $dispatcher,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        if (!$this->config->isEnabled() || !$this->config->isFrontendTrackerEnabled()) {
            return $result->setData(['ok' => false, 'error' => 'disabled'])->setHttpResponseCode(503);
        }

        $raw = (string) $this->getRequest()->getContent();
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
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'fbp', 'fbc'] as $k) {
            if (isset($body[$k]) && is_string($body[$k]) && $body[$k] !== '') {
                $data[$k] = $body[$k];
            }
        }

        $this->dispatcher->dispatch($eventName, $data, '', true, (int) $this->storeManager->getStore()->getId());
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
