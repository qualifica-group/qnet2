<?php

use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Bug 2026-10-05 (user decision: "cancella le righe"): replacing the product
 * line from the grid's "Categoria prodotto" cell left the offer rows on the
 * products of the category just removed, and the next offer save put that
 * category back (OpportunityProductLineCoverage). A classification change that
 * does not carry `offer_lines` now deletes the rows it no longer covers — the
 * same final state the work panel reaches when it empties them and saves.
 */
uses(RefreshDatabase::class);

function offerPruneActor(): User
{
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['request-management.viewAny', 'request-management.update']);

    return $user;
}

function offerPruneCategory(): ProductCategory
{
    return ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'is_selectable' => true,
    ]);
}

/** @param  array<int, ProductCategory>  $categories */
function offerPruneRequest(User $operator, array $categories): Quote
{
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

    foreach ($categories as $category) {
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);
    }

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
}

function offerPruneLine(Quote $quote, ProductCategory $category, int $sortOrder = 0): QuoteLine
{
    return QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'line_type' => QuoteLineType::Revenue,
        'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
        'sort_order' => $sortOrder,
    ]);
}

/** @param  array<int, ProductCategory>  $categories */
function offerPruneCellPatch(Quote $quote, array $categories)
{
    return test()->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'product_categories',
        'value' => array_map(static fn (ProductCategory $category): array => [
            'business_function_id' => (int) $category->business_function_id,
            'product_category_id' => $category->id,
        ], $categories),
    ]);
}

it('deletes the offer rows whose product the replaced product line no longer covers', function () {
    $actor = offerPruneActor();
    $removed = offerPruneCategory();
    $replacement = offerPruneCategory();
    $quote = offerPruneRequest($actor, [$removed]);
    $line = offerPruneLine($quote, $removed);
    Sanctum::actingAs($actor);

    offerPruneCellPatch($quote, [$replacement])->assertOk();

    expect(QuoteLine::query()->whereKey($line->id)->exists())->toBeFalse()
        ->and($quote->opportunity->productLines()->pluck('product_category_id')->all())->toBe([$replacement->id]);
});

it('keeps the offer rows the remaining product lines still cover', function () {
    $actor = offerPruneActor();
    $removed = offerPruneCategory();
    $kept = offerPruneCategory();
    $quote = offerPruneRequest($actor, [$removed, $kept]);
    $droppedLine = offerPruneLine($quote, $removed);
    $keptLine = offerPruneLine($quote, $kept, 1);
    Sanctum::actingAs($actor);

    offerPruneCellPatch($quote, [$kept])->assertOk();

    expect(QuoteLine::query()->whereKey($droppedLine->id)->exists())->toBeFalse()
        ->and(QuoteLine::query()->whereKey($keptLine->id)->exists())->toBeTrue();
});

it('leaves the offer rows alone when the classification does not change', function () {
    $actor = offerPruneActor();
    $category = offerPruneCategory();
    $quote = offerPruneRequest($actor, [$category]);
    $line = offerPruneLine($quote, $category);
    Sanctum::actingAs($actor);

    offerPruneCellPatch($quote, [$category])->assertOk();

    expect(QuoteLine::query()->whereKey($line->id)->exists())->toBeTrue();
});

it('never deletes a cost row, which no product line scopes', function () {
    $actor = offerPruneActor();
    $removed = offerPruneCategory();
    $replacement = offerPruneCategory();
    $quote = offerPruneRequest($actor, [$removed]);
    $costLine = QuoteLine::factory()->cost()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['category_id' => $removed->id])->id,
    ]);
    Sanctum::actingAs($actor);

    offerPruneCellPatch($quote, [$replacement])->assertOk();

    expect(QuoteLine::query()->whereKey($costLine->id)->exists())->toBeTrue();
});

it('refuses the change without touching the rows for an actor who may not update', function () {
    $actor = offerPruneActor();
    $actor->revokePermissionTo('request-management.update');
    $removed = offerPruneCategory();
    $quote = offerPruneRequest($actor, [$removed]);
    $line = offerPruneLine($quote, $removed);
    Sanctum::actingAs($actor);

    offerPruneCellPatch($quote, [offerPruneCategory()])->assertForbidden();

    expect(QuoteLine::query()->whereKey($line->id)->exists())->toBeTrue();
});
