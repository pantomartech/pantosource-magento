<?php
declare(strict_types=1);

namespace Panto\Source\Plugin;

use Magento\Quote\Api\CartItemRepositoryInterface;
use Panto\Source\Model\CartAddEmitter;

class CartItemRepositorySavePlugin
{
    public function __construct(
        private readonly CartAddEmitter $cartAddEmitter
    ) {
    }

    public function afterSave(CartItemRepositoryInterface $subject, mixed $result, mixed $cartItem): mixed
    {
        unset($subject);
        if (is_object($result) && is_object($cartItem)) {
            $this->cartAddEmitter->emitAfterRepositorySave($cartItem, $result);
        }
        return $result;
    }
}
