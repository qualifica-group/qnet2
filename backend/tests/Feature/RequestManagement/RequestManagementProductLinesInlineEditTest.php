<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0075: "Categoria prodotto" edited IN-CELL on the request-management
 * grid — PATCH /api/tables/request-management/rows/{row} with
 * `column: product_categories` and the whole {funzione aziendale, categoria}
 * collection as its value. Spec 0086, D-1: the row is now a `quotes` record;
 * the classification itself stays Opportunity-level (`quote.opportunity`).
 *
 * The point of every case below is that this channel has NO FormRequest and
 * still refuses exactly what the work panel refuses: the rules live in
 * ProductLineSetValidator, applied by the ONE writer both channels reach
 * (RequestProductLineWriter), plus the coherence rule updateWork() already
 * ran for the products of interest.
 */
uses(RefreshDatabase::class);

if (! function_exists('inlineLinesActor')) {
    function inlineLinesActor(array $abilities = ['viewAny', 'update']): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('inlineLinesCategory')) {
    /** A selectable category carrying its own business function (no inheritance involved). */
    function inlineLinesCategory(bool $selectable = true): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'is_selectable' => $selectable,
        ]);
    }
}

if (! function_exists('inlineLinesRequest')) {
    /** A quote the actor supervises, already classified with $category. */
    function inlineLinesRequest(User $supervisor, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

if (! function_exists('inlineLinesPatch')) {
    /** @param  array<int, array<string, mixed>>  $value */
    function inlineLinesPatch(Quote $quote, array $value)
    {
        return test()->patchJson("/api/tables/request-management/rows/{$quote->id}", [
            'column' => 'product_categories',
            'value' => $value,
        ]);
    }
}

if (! function_exists('inlineLinesPair')) {
    /** @return array<string, int> */
    function inlineLinesPair(ProductCategory $category): array
    {
        return [
            'business_function_id' => (int) $category->business_function_id,
            'product_category_id' => $category->id,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-002 — the happy path
// ---------------------------------------------------------------------------

it('AC-002: a valid pair replaces the classification and comes back on the row', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    $replacement = inlineLinesCategory();
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($replacement)])
        ->assertOk()
        ->assertJsonPath('data.product_categories.0.product_category_id', $replacement->id)
        ->assertJsonPath('data.product_categories.0.business_function_id', (int) $replacement->business_function_id)
        ->assertJsonPath('data.product_categories.0.product_category_name', $replacement->name)
        ->assertJsonCount(1, 'data.product_categories');

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $replacement->id,
    ]);
    $this->assertDatabaseMissing('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

it('AC-002: several pairs are written in one commit', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    // Spec 0077 INV-1/INV-2: a card's rows must share the same root and
    // business function — $second is a CHILD of $category, not an
    // independent root/function, so the two pairs stay a valid card.
    $second = ProductCategory::factory()->childOf($category)->create(['business_function_id' => $category->business_function_id]);
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($category), inlineLinesPair($second)])
        ->assertOk()
        ->assertJsonCount(2, 'data.product_categories');

    expect($quote->opportunity->productLines()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-003 / AC-004 / AC-005 / AC-006 — every rule of the set, on this channel
// ---------------------------------------------------------------------------

it('AC-003: a category outside the paired business function is refused', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    $other = inlineLinesCategory();
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [[
        'business_function_id' => (int) $category->business_function_id,
        'product_category_id' => $other->id,
    ]])->assertStatus(422);

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

it('AC-004: an unselectable category is refused, one already persisted is not', function () {
    $actor = inlineLinesActor();
    $persisted = inlineLinesCategory(selectable: false);
    $quote = inlineLinesRequest($actor, $persisted);
    $unselectable = inlineLinesCategory(selectable: false);
    // Spec 0077 INV-1/INV-2: shares $persisted's root and business function
    // so the two-row submission below stays a valid card.
    $selectable = ProductCategory::factory()->childOf($persisted)->create(['business_function_id' => $persisted->business_function_id]);
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($unselectable)])->assertStatus(422);

    // The category the request ALREADY carries stays writable alongside a new
    // one (spec 0074 D-3b exemption, honoured on this channel too).
    inlineLinesPatch($quote, [inlineLinesPair($persisted), inlineLinesPair($selectable)])
        ->assertOk()
        ->assertJsonCount(2, 'data.product_categories');
});

it('AC-005: the same pair twice is refused', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    $second = inlineLinesCategory();
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($second), inlineLinesPair($second)])
        ->assertStatus(422);

    expect($quote->opportunity->productLines()->count())->toBe(1);
});

it('AC-006: the classification can never be cleared in-cell', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [])->assertStatus(422);

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

it('AC-006: a malformed pair is refused before it reaches the writer', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [['product_category_id' => $category->id]])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-007 — the coherence rule with the products of interest
// ---------------------------------------------------------------------------

it('AC-007: dropping a category whose product is still selected is refused', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $quote->opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = inlineLinesCategory();
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($replacement)])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'These products of interest belong to a product category the request does not carry: "Fibra 1000" ('.$category->name.'). Add that product category to the request, or remove the product.']);

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $quote->opportunity_id,
        'product_category_id' => $category->id,
    ]);
});

// ---------------------------------------------------------------------------
// AC-008 — authorization
// ---------------------------------------------------------------------------

it('AC-008: without request-management.update the column is read-only and the PATCH is 403', function () {
    $actor = inlineLinesActor(['viewAny']);
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['product_categories']['editable'])->toBeFalse();

    inlineLinesPatch($quote, [inlineLinesPair(inlineLinesCategory())])->assertStatus(403);
});

// ---------------------------------------------------------------------------
// AC-010 — the workflow criterion still re-resolves from this channel
// ---------------------------------------------------------------------------

it('AC-010: the commit goes through updateWork, audit entry included', function () {
    $actor = inlineLinesActor();
    $category = inlineLinesCategory();
    $quote = inlineLinesRequest($actor, $category);
    $replacement = inlineLinesCategory();
    Sanctum::actingAs($actor);

    inlineLinesPatch($quote, [inlineLinesPair($replacement)])->assertOk();

    // The explicit audit entry only exists inside updateWork() (the collection
    // is a relation, invisible to the automatic fillable diff): finding it is
    // what proves the cell went through the whole pipeline — workflow
    // re-resolution (step 6) included — and not through a bare relation sync.
    $opportunity = $quote->opportunity;
    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->latest('id')
        ->first();

    expect($activity?->properties['attributes']['product_lines'] ?? null)->toBeArray();
});
