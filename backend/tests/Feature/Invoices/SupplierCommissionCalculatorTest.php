<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Services\Invoices\SupplierCommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Spec 0288 D-4: the supplier commission on the sale net amount of a line.
 */
uses(RefreshDatabase::class);

function supplierSoldLine(Registry $supplier, ?ProductCategory $category = null): QuoteLine
{
    $product = Product::factory()->create(['supplier_id' => $supplier->id, ...($category === null ? [] : ['category_id' => $category->id])]);

    return QuoteLine::factory()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 500, 'net_amount' => 1000])->load('product');
}

function supplierRule(Registry $supplier, CommissionApplicationScope $scope, array $attributes): CommissionConfiguration
{
    return CommissionConfiguration::factory()
        ->forRecipient(CommissionRecipientRole::Supplier->recipientType(), $supplier->id, $scope)
        ->create(['recipient_role' => CommissionRecipientRole::Supplier, 'valid_from' => '2026-01-01', ...$attributes]);
}

function commissionOf(QuoteLine $line)
{
    return app(SupplierCommissionCalculator::class)->forLine($line, new DateTimeImmutable('2026-10-21'));
}

it('AC-005: a percentage rule on the product bills that share of the sale net amount', function () {
    $supplier = Registry::factory()->create();
    $line = supplierSoldLine($supplier);
    supplierRule($supplier, CommissionApplicationScope::Product, ['product_id' => $line->product_id, 'value' => 10]);

    $commission = commissionOf($line);

    expect($commission->type)->toBe(CommissionType::Percentage)
        ->and((float) $commission->value)->toBe(10.0)
        ->and($commission->baseAmount)->toBe('1000.00')
        ->and($commission->amount)->toBe('100.00');
});

it('AC-006: without a product rule the rule of an ancestor category applies', function () {
    $supplier = Registry::factory()->create();
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $line = supplierSoldLine($supplier, $child);
    supplierRule($supplier, CommissionApplicationScope::ProductCategory, ['product_category_id' => $parent->id, 'value' => 7.5]);

    expect(commissionOf($line)->amount)->toBe('75.00');
});

it('AC-007: a fixed amount rule bills the amount once per line, whatever the quantity', function () {
    $supplier = Registry::factory()->create();
    $line = supplierSoldLine($supplier);
    supplierRule($supplier, CommissionApplicationScope::Product, [
        'product_id' => $line->product_id, 'commission_type' => CommissionType::FixedAmount, 'value' => 150,
    ]);

    $commission = commissionOf($line);

    expect($commission->type)->toBe(CommissionType::FixedAmount)
        ->and($commission->amount)->toBe('150.00');
});

it('AC-008: no applicable rule bills zero, another supplier rule is ignored', function () {
    $supplier = Registry::factory()->create();
    $line = supplierSoldLine($supplier);
    supplierRule(Registry::factory()->create(), CommissionApplicationScope::Product, ['product_id' => $line->product_id, 'value' => 10]);

    $commission = commissionOf($line);

    expect($commission->type)->toBeNull()
        ->and($commission->value)->toBeNull()
        ->and($commission->amount)->toBe('0.00');
});
