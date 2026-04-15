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
use Panto\Source\Model\DebugTimeline;

class Marker extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKey $formKey,
        private readonly ConfigInterface $config,
        private readonly DebugTimeline $debugTimeline,
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
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->isDebugEnabled($storeId)) {
            return $result->setData(['ok' => false, 'error' => 'disabled'])->setHttpResponseCode(404);
        }

        $raw = (string) $this->getRequest()->getContent();
        $body = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($body)) {
            return $result->setData(['ok' => false, 'error' => 'invalid_json'])->setHttpResponseCode(400);
        }

        $fk = isset($body['form_key']) ? (string) $body['form_key'] : '';
        if ($fk === '' || !hash_equals($this->formKey->getFormKey(), $fk)) {
            return $result->setData(['ok' => false, 'error' => 'csrf'])->setHttpResponseCode(403);
        }

        $actionName = isset($body['action_name']) ? (string) $body['action_name'] : '';
        $eventName = isset($body['event_name']) ? (string) $body['event_name'] : '';
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

        $this->debugTimeline->append([
            'source' => 'frontend_js',
            'hook_or_action' => substr($actionName, 0, 128),
            'event_name' => substr($eventName, 0, 128),
            'event_id' => '',
            'status' => 'marker',
            'context' => ['store_id' => $storeId],
            'payload' => $data,
        ], $storeId);

        return $result->setData(['ok' => true]);
    }
}
