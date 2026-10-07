<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Customer\Model\Session as CustomerSession;

/**
 * Session claim so SPA JS skips product_viewed after ProductViewObserver already emitted.
 */
class ProductViewedClaim
{
    public const KEY_PREFIX = 'pantosource_product_viewed_';

    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    public static function sessionKey(int $productId): string
    {
        return self::KEY_PREFIX . $productId;
    }

    public function claim(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }
        $this->customerSession->setData(self::sessionKey($productId), 1);
    }

    public function isClaimed(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }
        return (bool) $this->customerSession->getData(self::sessionKey($productId));
    }
}
