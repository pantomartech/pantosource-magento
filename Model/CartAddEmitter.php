<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\CartAddEmissionGate;
use Panto\Source\Helper\QuoteItemFields;
use Panto\Source\Helper\StorefrontContext;

/**
 * Shared magento_add_to_cart emit + request-scoped dedupe for observers and repository plugins.
 */
class CartAddEmitter
{
    public const SKIP_ADMIN = 'skipped_admin';
    public const SKIP_NO_ITEM = 'skipped_no_item';
    public const SKIP_DEDUPE = 'skipped_dedupe';
    public const SKIP_NOT_QTY_INCREASE = 'skipped_not_qty_increase';
    public const OBSERVED = 'observed';

    public function __construct(
        private readonly EventDispatcher $dispatcher,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CartAddEmissionGate $emissionGate,
        private readonly DebugTimeline $debugTimeline
    ) {
    }

    public function emitFromQuoteItem(mixed $item): bool
    {
        if (!is_object($item)) {
            $this->markSkip(self::SKIP_NO_ITEM, ['reason' => 'not_object']);
            return false;
        }

        $productId = QuoteItemFields::int($item, 'product_id', 'getProductId');
        if ($productId <= 0) {
            $this->markSkip(self::SKIP_NO_ITEM, ['reason' => 'product_id=0']);
            return false;
        }

        $qty = QuoteItemFields::float($item, 'qty', 'getQty');
        $cartId = $this->resolveCartId($item);
        $itemId = $this->readItemId($item);
        $dedupeKey = CartAddEmissionGate::dedupeKey($itemId, $cartId, $productId, $qty);
        if (!$this->emissionGate->shouldEmit($dedupeKey)) {
            $this->markSkip(self::SKIP_DEDUPE, ['dedupe_key' => $dedupeKey]);
            return false;
        }

        $this->dispatcher->dispatch('magento_add_to_cart', $this->buildData($item, $productId, $qty, $cartId, $itemId));
        $this->markSkip(self::OBSERVED, ['product_id' => $productId, 'cart_id' => $cartId]);
        return true;
    }

    public function emitAfterRepositorySave(object $input, object $result): bool
    {
        $inputId = $this->readItemId($input);
        $origQty = QuoteItemFields::origFloat($input, 'qty') ?? 0.0;
        $newQty = QuoteItemFields::float($result, 'qty', 'getQty');
        if ($inputId > 0 && !($origQty > 0 && $newQty > $origQty)) {
            $this->markSkip(self::SKIP_NOT_QTY_INCREASE, ['item_id' => $inputId]);
            return false;
        }
        return $this->emitFromQuoteItem($result);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function markSkip(string $reason, array $context): void
    {
        $this->debugTimeline->append([
            'source' => 'cart_add',
            'hook_or_action' => $reason,
            'event_name' => 'magento_add_to_cart',
            'event_id' => '',
            'status' => $reason,
            'context' => $context,
            'payload' => [],
        ]);
    }

    private function readItemId(object $item): int
    {
        $itemId = QuoteItemFields::int($item, 'item_id', 'getItemId');
        if ($itemId > 0) {
            return $itemId;
        }
        return QuoteItemFields::int($item, 'id', 'getId');
    }

    private function resolveCartId(mixed $item): int
    {
        try {
            $quote = is_object($item) && is_callable([$item, 'getQuote']) ? $item->getQuote() : null;
            if ($quote && $quote->getId()) {
                return (int) $quote->getId();
            }
        } catch (\Throwable) {
        }
        return is_object($item) ? QuoteItemFields::int($item, 'quote_id', 'getQuoteId') : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(mixed $item, int $productId, float $qty, int $cartId, int $itemId): array
    {
        $rowTotal = QuoteItemFields::float($item, 'row_total', 'getRowTotal');
        $unitPrice = $qty > 0 && $rowTotal > 0
            ? $rowTotal / $qty
            : QuoteItemFields::float($item, 'price', 'getPrice');
        $title = QuoteItemFields::string($item, 'name', 'getName');
        $sku = QuoteItemFields::string($item, 'sku', 'getSku');
        $category = $this->resolveCategory($item);
        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (\Throwable) {
            $currency = '';
        }
        $data = [
            'product_id' => $productId,
            'quantity' => (int) $qty,
            'sku' => $sku,
            'title' => $title,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'quote_item_id' => $itemId,
            'category' => $category,
            'items' => [[
                'product_id' => $productId,
                'title' => $title,
                'sku' => $sku,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'category' => $category,
            ]],
            'context' => StorefrontContext::fromRequest($this->request, 'cart', ''),
        ];
        if ($cartId > 0) {
            $data['cart_id'] = $cartId;
        }
        return $data;
    }

    private function resolveCategory(mixed $item): string
    {
        try {
            $product = is_object($item) && is_callable([$item, 'getProduct']) ? $item->getProduct() : null;
            if ($product === null) {
                return '';
            }
            $catIds = $product->getCategoryIds();
            if (empty($catIds)) {
                return '';
            }
            return (string) ($this->categoryRepository->get((int) $catIds[0])->getName() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
