<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Request-scoped dedupe so checkout_cart_product_add_after and sales_quote_product_add_after
 * do not emit magento_add_to_cart twice for the same Luma add.
 */
class CartAddEmissionGate
{
    private const BAG_PARAM = '__pantosource_atc_emitted';

    public function __construct(
        private readonly HttpRequest $request
    ) {
    }

    public function shouldEmit(string $dedupeKey): bool
    {
        if ($dedupeKey === '') {
            return false;
        }
        $bag = $this->request->getParam(self::BAG_PARAM);
        if (!is_array($bag)) {
            $bag = [];
        }
        if (isset($bag[$dedupeKey])) {
            return false;
        }
        $bag[$dedupeKey] = true;
        $this->request->setParam(self::BAG_PARAM, $bag);
        return true;
    }

    public static function dedupeKey(int $itemId, int $quoteId, int $productId, float $qty): string
    {
        if ($itemId > 0) {
            return 'item:' . $itemId;
        }
        if ($quoteId <= 0 && $productId <= 0) {
            return '';
        }
        return 'qp:' . $quoteId . ':' . $productId . ':' . $qty;
    }
}
