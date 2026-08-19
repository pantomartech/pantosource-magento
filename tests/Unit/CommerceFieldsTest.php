<?php
declare(strict_types=1);

namespace Panto\Source\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Panto\Source\Model\CommerceFields;

final class CommerceFieldsTest extends TestCase
{
    public function testPaymentMethodFromOrderTotalsWins(): void
    {
        $method = CommerceFields::resolvePaymentMethod([
            'order_totals' => ['payment_method' => 'checkmo'],
            'payment_method' => 'ignored',
        ]);
        $this->assertSame('checkmo', $method);
    }

    public function testPaymentMethodFallsBackToData(): void
    {
        $method = CommerceFields::resolvePaymentMethod([
            'payment_method' => '  paypal_express  ',
        ]);
        $this->assertSame('paypal_express', $method);
    }

    public function testEmptyTotalsPaymentFallsBackToData(): void
    {
        $method = CommerceFields::resolvePaymentMethod([
            'order_totals' => ['payment_method' => '  '],
            'payment_method' => 'stripe_payments',
        ]);
        $this->assertSame('stripe_payments', $method);
    }

    public function testHoistProductNameSkuPrice(): void
    {
        $product = CommerceFields::hoistProduct([
            'product_id' => 42,
            'product_name' => 'Tee',
            'sku' => 'TEE-1',
            'price' => '19.99',
        ]);
        $this->assertSame('42', $product['product_id']);
        $this->assertSame('Tee', $product['name']);
        $this->assertSame('TEE-1', $product['sku']);
        $this->assertSame(19.99, $product['price']);
    }

    public function testHoistProductUsesTitleAndUnitPrice(): void
    {
        $product = CommerceFields::hoistProduct([
            'product_id' => '9',
            'title' => 'Hat',
            'unit_price' => 12,
        ]);
        $this->assertSame('Hat', $product['name']);
        $this->assertSame(12.0, $product['price']);
    }

    public function testHoistProductMapsVariationIdToVariantId(): void
    {
        $product = CommerceFields::hoistProduct([
            'product_id' => '11',
            'variation_id' => '22',
            'product_name' => 'Blue Shirt',
            'unit_price' => 19.99,
        ]);
        $this->assertSame('11', $product['product_id']);
        $this->assertSame('22', $product['variation_id']);
        $this->assertSame('22', $product['variant_id']);
        $this->assertSame('Blue Shirt', $product['name']);
        $this->assertSame(19.99, $product['price']);
    }

    public function testLineVariantIdSkipsZeroAndPrefersVariationId(): void
    {
        $this->assertSame('', CommerceFields::lineVariantId(['product_id' => '11', 'variation_id' => 0]));
        $this->assertSame('22', CommerceFields::lineVariantId(['variation_id' => 0, 'variant_id' => '22']));
        $this->assertSame('33', CommerceFields::lineVariantId(['variation_id' => '33', 'variant_id' => '99']));
    }
}
