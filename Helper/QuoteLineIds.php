<?php
declare(strict_types=1);

namespace Panto\Source\Helper;

/**
 * Resolve Magento configurable/bundle child id as catalog variant_id.
 */
class QuoteLineIds
{
    public static function variantId(mixed $item, int $productId): int
    {
        if (!is_object($item) || $productId <= 0) {
            return 0;
        }

        if (is_callable([$item, 'getParentItem'])) {
            try {
                $parent = $item->getParentItem();
                if (is_object($parent)) {
                    $parentId = QuoteItemFields::int($parent, 'product_id', 'getProductId');
                    if ($parentId > 0 && $parentId !== $productId) {
                        return $productId;
                    }
                }
            } catch (\Throwable) {
            }
        }

        foreach (['getChildren', 'getChildrenItems'] as $method) {
            if (!is_callable([$item, $method])) {
                continue;
            }
            try {
                $children = $item->{$method}();
            } catch (\Throwable) {
                continue;
            }
            if (!is_iterable($children)) {
                continue;
            }
            foreach ($children as $child) {
                $childId = QuoteItemFields::int($child, 'product_id', 'getProductId');
                if ($childId > 0 && $childId !== $productId) {
                    return $childId;
                }
            }
        }

        if (!is_callable([$item, 'getOptionByCode'])) {
            return 0;
        }
        try {
            $opt = $item->getOptionByCode('simple_product');
            if (!is_object($opt)) {
                return 0;
            }
            if (is_callable([$opt, 'getProduct'])) {
                $simple = $opt->getProduct();
                if (is_object($simple) && is_callable([$simple, 'getId'])) {
                    $id = (int) $simple->getId();
                    if ($id > 0 && $id !== $productId) {
                        return $id;
                    }
                }
            }
            if (is_callable([$opt, 'getValue'])) {
                $id = (int) $opt->getValue();
                if ($id > 0 && $id !== $productId) {
                    return $id;
                }
            }
        } catch (\Throwable) {
        }

        return 0;
    }
}
