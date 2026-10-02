<?php

declare(strict_types=1);

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0188, AC-009: a classification on a node that overrides a simplified
// root to false behaves as a plain (non simplified) one — no freeze. The
// normalizer reads the denormalised effective column only. Self-contained
// (no helpers shared with SimplifiedOfferLineFreezeTest: parallel workers
// load one file each).

uses(RefreshDatabase::class);

it('AC-009: an offer line on an overriding-false category under a simplified root persists the submitted quantity and price', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.create', 'request-management.viewAll']);

    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple, 'simplified_offer_line' => true]);
    $category = ProductCategory::factory()->childOf($root)->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'management_mode' => CategoryManagementMode::Multiple,
        'simplified_offer_line' => false,
        'simplified_offer_line_override' => false,
    ]);

    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 150.00, 'vat_rate_id' => $vatRate->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'vat_rate_id' => $vatRate->id,
        ]],
    ])->assertOk();

    $line = $quote->offerLines()->sole();
    expect((float) $line->quantity)->toBe(3.0)
        ->and((float) $line->unit_price)->toBe(100.0);
});
