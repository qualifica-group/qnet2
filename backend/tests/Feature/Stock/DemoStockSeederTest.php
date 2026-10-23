<?php

use App\Enums\StockMovementType;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DemoStockSeeder;
use Database\Seeders\ProductTypologySeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('0307 D-20: the demo seeder writes tracked products with a history of every type, and a re-run adds nothing', function () {
    $this->seed(UnitOfMeasureSeeder::class);
    $this->seed(ProductTypologySeeder::class);
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(3)->create();

    $this->seed(DemoStockSeeder::class);
    $movements = StockMovement::count();
    $balances = StockBalance::count();

    $this->seed(DemoStockSeeder::class);

    expect(Product::query()->where('product_type', 'PHYSICAL')->count())->toBe(3)
        ->and(Product::all()->every->isStockTracked())->toBeTrue()
        ->and(StockMovement::query()->distinct()->pluck('type')->map->value->sort()->values()->all())
        ->toBe(collect(StockMovementType::cases())->map->value->sort()->values()->all())
        ->and(StockMovement::count())->toBe($movements)
        ->and(StockBalance::count())->toBe($balances)
        ->and($balances)->toBeGreaterThan(0)
        ->and(StockBalance::query()->where('quantity', '<=', 0)->exists())->toBeFalse();
});
