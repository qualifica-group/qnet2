<?php

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Spec 0098 (D-1/D-5): `work_orders.attribute_values` is a JSON nullable
// column, cast to array, deliberately NOT mass-assignable (written
// exclusively by WorkOrderAttributeValueWriter) — the Commessa twin of
// `quotes.attribute_values` (spec 0084).

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — enum
// ---------------------------------------------------------------------------

it('AC-001: AttributeContext::WorkOrder inheritanceColumn() is inherits_work_order_attributes', function () {
    expect(AttributeContext::WorkOrder->value)->toBe('work_order')
        ->and(AttributeContext::WorkOrder->inheritanceColumn())->toBe('inherits_work_order_attributes');
});

// ---------------------------------------------------------------------------
// AC-002 — migration reversibility
// ---------------------------------------------------------------------------

it('AC-002: the migration is reversible, down() drops both columns', function () {
    expect(Schema::hasColumn('product_categories', 'inherits_work_order_attributes'))->toBeTrue()
        ->and(Schema::hasColumn('work_orders', 'attribute_values'))->toBeTrue();

    $migration = require database_path('migrations/2026_09_02_240000_add_work_order_attribute_context_columns.php');

    $migration->down();

    expect(Schema::hasColumn('product_categories', 'inherits_work_order_attributes'))->toBeFalse()
        ->and(Schema::hasColumn('work_orders', 'attribute_values'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('product_categories', 'inherits_work_order_attributes'))->toBeTrue()
        ->and(Schema::hasColumn('work_orders', 'attribute_values'))->toBeTrue();
});

it('adds the attribute_values json nullable column to work_orders, defaulting null', function () {
    $workOrder = WorkOrder::factory()->create();

    expect($workOrder->attribute_values)->toBeNull();
});

it('inherits_work_order_attributes defaults to true on a fresh category', function () {
    $category = ProductCategory::factory()->create();

    expect($category->inherits_work_order_attributes)->toBeTrue();
});

it('casts attribute_values to array on the model', function () {
    $workOrder = WorkOrder::factory()->create();

    $workOrder->forceFill(['attribute_values' => ['warehouse_size' => 120, 'has_forklift' => true]])->save();
    $workOrder->refresh();

    expect($workOrder->attribute_values)->toBeArray()
        ->and($workOrder->attribute_values)->toBe(['warehouse_size' => 120, 'has_forklift' => true]);
});

it('attribute_values is NOT mass-assignable (absent from Fillable, D-5)', function () {
    $quote = Quote::factory()->create();

    $workOrder = new WorkOrder([
        'quote_id' => $quote->id,
        'title' => 'Test work order',
        'type' => 'processing',
        'start_date' => '2026-09-10',
        'attribute_values' => ['warehouse_size' => 120],
    ]);
    $workOrder->code = 'COM-9999';
    $workOrder->save();

    expect($workOrder->attribute_values)->toBeNull();

    $workOrder->fill(['attribute_values' => ['warehouse_size' => 999]]);

    expect($workOrder->attribute_values)->toBeNull();
});
