<?php

use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatRate;
use Database\Seeders\QualificaCostProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The legacy cost items as new qnet products (user directive 2026-10-16):
// active "Costi" branch, cost-only usage, no legacy anchor.
uses(RefreshDatabase::class);

it('files every cost item under the active Costi branch, usable only as a cost', function (): void {
    test()->seed(QualificaCostProductSeeder::class);

    $root = ProductCategory::query()->where('name', 'Costi')->whereNull('parent_id')->sole();
    $leaves = ProductCategory::query()->where('parent_id', $root->id)->orderBy('id')->get();
    $products = Product::query()->whereIn('category_id', $leaves->pluck('id'))->get();

    expect($root->is_active)->toBeTrue()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBeNull()
        ->and($leaves->pluck('name')->all())->toBe(['Note spese', 'Articoli', 'Veicoli', 'Attrezzature'])
        ->and($leaves->every(fn (ProductCategory $leaf): bool => $leaf->is_active && $leaf->is_selectable))->toBeTrue()
        ->and($products)->toHaveCount(17)
        ->and($products->every(fn (Product $product): bool => $product->usages->all() === [ProductUsage::Cost]))->toBeTrue()
        ->and($products->every(fn (Product $product): bool => $product->old_id === null && $product->old_source === null))->toBeTrue()
        ->and(Product::query()->where('name', 'like', '[OLD]%')->exists())->toBeFalse();
});

it('carries the legacy amount as both cost and price, and the 22% rate only where the legacy had it', function (): void {
    test()->seed(QualificaCostProductSeeder::class);

    $teachers = Product::query()->where('name', 'Docenti formazione')->sole();
    $tolls = Product::query()->where('name', 'Pedaggi')->sole();
    $plusVat = VatRate::query()->where('rate', 22)->sole();

    expect((float) $teachers->cost)->toBe(20.0)
        ->and((float) $teachers->price)->toBe(20.0)
        ->and($teachers->vat_rate_id)->toBeNull()
        ->and($teachers->description)->toBe('Costo dei docenti della formazione')
        ->and($tolls->vat_rate_id)->toBe($plusVat->id);
});

it('duplicates nothing on a re-run and keeps a manual edit', function (): void {
    test()->seed(QualificaCostProductSeeder::class);
    Product::query()->where('name', 'Spese varie')->sole()->update(['cost' => 5, 'price' => 5]);

    test()->seed(QualificaCostProductSeeder::class);

    expect(ProductCategory::query()->where('name', 'Costi')->count())->toBe(1)
        ->and(Product::query()->count())->toBe(17)
        ->and((float) Product::query()->where('name', 'Spese varie')->sole()->cost)->toBe(5.0)
        ->and(VatRate::query()->count())->toBe(1);
});
