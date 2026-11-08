<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Request-scoped dedupe so the quote-item events, the checkout/cart/add postdispatch observer and
 * the cart item repository plugins emit magento_add_to_cart once per cart line.
 *
 * Lines are keyed by quote item id only. A line that is not saved yet has no id, so it is held as
 * pending and emitted once it is saved (sales_quote_item_save_commit_after); keying it on product
 * and qty instead never matches the saved line's key, which emitted every new line twice.
 */
class CartAddEmissionGate
{
    private const BAG_PARAM = '__pantosource_atc_emitted';

    /** @var array<int, object> unsaved quote items awaiting their id, by spl_object_id */
    private array $pending = [];

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

    public static function dedupeKey(int $itemId): string
    {
        return $itemId > 0 ? 'item:' . $itemId : '';
    }

    public function defer(object $item): void
    {
        $this->pending[spl_object_id($item)] = $item;
    }

    /** True once for an item previously passed to defer(); removes it from the pending set. */
    public function takePending(object $item): bool
    {
        $id = spl_object_id($item);
        if (!isset($this->pending[$id]) || $this->pending[$id] !== $item) {
            return false;
        }
        unset($this->pending[$id]);
        return true;
    }
}
