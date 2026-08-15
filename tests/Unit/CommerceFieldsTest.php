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
}
