<?php

use App\Models\CostType;
use App\Models\Product;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const COST_TYPE_COLUMNS_MIGRATION = 'migrations/2026_10_17_190000_add_cost_type_to_products_and_work_order_costs.php';

it('AC-001: the columns exist and rollback drops them', function () {
    $migration = require database_path(COST_TYPE_COLUMNS_MIGRATION);

    expect(Schema::hasColumn('products', 'cost_type_id'))->toBeTrue()
        ->and(Schema::hasColumn('work_order_costs', 'cost_type_id'))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('products', 'cost_type_id'))->toBeFalse()
        ->and(Schema::hasColumn('work_order_costs', 'cost_type_id'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('products', 'cost_type_id'))->toBeTrue()
        ->and(Schema::hasColumn('work_order_costs', 'cost_type_id'))->toBeTrue();
});

it('AC-001: the backfill gives existing costs the cost type of their product', function () {
    $migration = require database_path(COST_TYPE_COLUMNS_MIGRATION);
    $costType = CostType::factory()->create();
    $typed = Product::factory()->costOnly()->create(['cost_type_id' => $costType->id]);
    $untyped = Product::factory()->costOnly()->create();
    $fromTyped = WorkOrderCost::factory()->create(['product_id' => $typed->id]);
    $fromUntyped = WorkOrderCost::factory()->create(['product_id' => $untyped->id]);

    $migration->backfill();

    expect(DB::table('work_order_costs')->where('id', $fromTyped->id)->value('cost_type_id'))->toBe($costType->id)
        ->and(DB::table('work_order_costs')->where('id', $fromUntyped->id)->value('cost_type_id'))->toBeNull();
});
