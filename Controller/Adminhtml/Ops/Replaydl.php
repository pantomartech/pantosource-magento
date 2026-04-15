<?php
declare(strict_types=1);

namespace Panto\Source\Controller\Adminhtml\Ops;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Panto\Source\Model\QueueBroker;

class Replaydl extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panto_Source::ops';

    private const REPLAY_CACHE_KEY = 'pantosource_dl_replay_ts';

    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly QueueBroker $queueBroker,
        private readonly CacheInterface $cache
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
        }

        $publicId = trim((string) $this->getRequest()->getParam('public_id', ''));
        if ($publicId === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $publicId)) {
            $this->messageManager->addErrorMessage(__('Enter a valid dead-letter public id (e.g. q_xxxxxxxxxxxxxx).'));
            return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
        }

        $prev = $this->cache->load(self::REPLAY_CACHE_KEY);
        if ($prev !== false && time() - (int) $prev < 10) {
            $this->messageManager->addErrorMessage(__('Replay is rate-limited. Please wait a few seconds.'));
            return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
        }

        $ok = $this->queueBroker->replayDeadLetter($publicId);
        $this->cache->save((string) time(), self::REPLAY_CACHE_KEY, [], 20);
        if ($ok) {
            $this->messageManager->addSuccessMessage(__('Dead-letter event was moved back to the retry queue.'));
        } else {
            $this->messageManager->addErrorMessage(__('No dead-letter row found for that public id.'));
        }

        return $this->resultRedirectFactory->create()->setPath('pantosource/ops/index');
    }
}
