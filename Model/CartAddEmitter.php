<?php
declare(strict_types=1);

namespace Panto\Source\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;
use Panto\Source\Helper\CartAddEmissionGate;
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
        if ($item === null || !method_exists($item, 'getProductId') || !$item->getProductId()) {
            $this->markSkip(self::SKIP_NO_ITEM, []);
            return false;
        }

        $qty = (float) $item->getQty();
        $productId = (int) $item->getProductId();
        $cartId = $this->resolveCartId($item);
        $itemId = method_exists($item, 'getId') ? (int) $item->getId() : $this->readItemId($item);
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
        $origQty = method_exists($input, 'getOrigData') ? (float) $input->getOrigData('qty') : 0.0;
        $newQty = method_exists($result, 'getQty') ? (float) $result->getQty() : 0.0;
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
        if (method_exists($item, 'getItemId') && $item->getItemId()) {
            return (int) $item->getItemId();
        }
        if (method_exists($item, 'getId') && $item->getId()) {
            return (int) $item->getId();
        }
        return 0;
    }

    private function resolveCartId(mixed $item): int
    {
        try {
            $quote = method_exists($item, 'getQuote') ? $item->getQuote() : null;
            if ($quote && $quote->getId()) {
                return (int) $quote->getId();
            }
        } catch (\Throwable) {
        }
        return method_exists($item, 'getQuoteId') ? (int) $item->getQuoteId() : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(mixed $item, int $productId, float $qty, int $cartId, int $itemId): array
    {
        $rowTotal = method_exists($item, 'getRowTotal') ? (float) $item->getRowTotal() : 0.0;
        $unitPrice = $qty > 0 && $rowTotal > 0 ? $rowTotal / $qty : (float) $item->getPrice();
        $title = method_exists($item, 'getName') ? (string) $item->getName() : '';
        $sku = method_exists($item, 'getSku') ? (string) $item->getSku() : '';
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
            $product = method_exists($item, 'getProduct') ? $item->getProduct() : null;
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
