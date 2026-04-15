<?php
declare(strict_types=1);

namespace Panto\Source\Observer;

use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Panto\Source\Helper\StorefrontContext;
use Panto\Source\Model\EventDispatcher;

class PageViewObserver implements ObserverInterface
{
    private static bool $sent = false;

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly State $appState
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (self::$sent) {
            return;
        }
        try {
            if ($this->appState->getAreaCode() !== \Magento\Framework\App\Area::AREA_FRONTEND) {
                return;
            }
        } catch (\Throwable) {
            return;
        }
        if ($this->request->isXmlHttpRequest()) {
            return;
        }
        self::$sent = true;
        $data = ['context' => StorefrontContext::fromRequest($this->request, 'page', '')];
        $this->dispatcher->dispatch('pageview', $data);
    }
}
