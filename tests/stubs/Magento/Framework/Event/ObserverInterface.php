<?php
declare(strict_types=1);

namespace Magento\Framework\Event;

interface ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer);
}
