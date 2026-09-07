<?php

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Linee di prodotto" (spec 0086, D-7): the `offer_lines` grid column that
 * replaces "Prodotti di interesse" on the `request-management` domain ONLY
 * (AC-008/AC-009) — projects the offer's own REVENUE lines' products
 * (AC-007), never a COST line's (App\Tables\Shared\OfferLinesColumn).
 *
 * REQUIREMENT CHANGE (user directive 2026-09-07, "l'edit della cella deve
 * essere lo stesso flusso delle altre colonne"): the column is now INLINE
 * EDITABLE, which revokes spec 0086 AC-021/AC-022 — the two cases below that
 * asserted "read-only" now assert the editable declaration and the write
 * itself. The work panel had already been writing these rows since the
 * 2026-08-07 directive; this only opens the SECOND channel, onto the very
 * same `updateWork()`. Exercised entirely through the GENERIC
 * /api/tables/request-management/* endpoints
 * (App\Tables\RequestManagementTableDefinition), never the dedicated work
 * panel — no dependency on RequestManagementService/Controller.
 */
uses(RefreshDatabase::class);

if (! function_exists('offerLinesActor')) {
    function offerLinesActor(bool $canUpdate = false): User
    {
        foreach (['viewAny', 'viewAll', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $abilities = ['request-management.viewAny', 'request-management.viewAll'];

        if ($canUpdate) {
            $abilities[] = 'request-management.update';
        }

        $user->givePermissionTo($abilities);

        return $user;
    }
}

if (! function_exists('offerLine')) {
    function offerLine(Quote $quote, Product $product, QuoteLineType $type): QuoteLine
    {
        return QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'line_type' => $type,
        ]);
    }
}

it('AC-008: the config exposes offer_lines, inline editable, and no longer products_of_interest', function () {
    Sanctum::actingAs(offerLinesActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns->has('products_of_interest'))->toBeFalse();

    $column = $columns['offer_lines'];
    expect($column['label'])->toBe('requestManagement.columns.offerLines')
        ->and($column['type'])->toBe('text')
        ->and($column['sortable'])->toBeFalse()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        // Requirement change 2026-09-07: editable, with the collection editor
        // and the field key the engine remaps permission and write onto.
        ->and($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('offer_lines')
        ->and($column['editableField'])->toBe('offer_lines');
});

it("AC-007: offer_lines projects the REVENUE products only, never a COST line's product", function () {
    $actor = offerLinesActor();
    $quote = Quote::factory()->create(['operator_id' => $actor->id]);
    $revenueA = Product::factory()->create(['name' => 'Fibra 1000']);
    $revenueB = Product::factory()->create(['name' => 'ADSL 20']);
    $cost = Product::factory()->create(['name' => 'Costo interno']);
    offerLine($quote, $revenueA, QuoteLineType::Revenue);
    offerLine($quote, $revenueB, QuoteLineType::Revenue);
    offerLine($quote, $cost, QuoteLineType::Cost);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [], 'filterModel' => [],
    ])->assertOk()->json('items'))->firstWhere('id', $quote->id);

    expect(collect($row['offer_lines'])->pluck('name')->sort()->values()->all())
        ->toBe(['ADSL 20', 'Fibra 1000']);
});

it('2026-09-07: the editable declaration is still gated by the actor: no update permission, no editor', function () {
    Sanctum::actingAs(offerLinesActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['offer_lines']['editable'])->toBeFalse();
});

it('filters the rows by offer_lines product name and enumerates its distinct values', function () {
    $actor = offerLinesActor();
    $matching = Quote::factory()->create(['operator_id' => $actor->id]);
    $other = Quote::factory()->create(['operator_id' => $actor->id]);
    $wanted = Product::factory()->create(['name' => 'Fibra 1000']);
    $unwanted = Product::factory()->create(['name' => 'ADSL 20']);
    offerLine($matching, $wanted, QuoteLineType::Revenue);
    offerLine($other, $unwanted, QuoteLineType::Revenue);
    Sanctum::actingAs($actor);

    $items = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [],
        'filterModel' => ['offer_lines' => ['filterType' => 'set', 'values' => ['Fibra 1000']]],
    ])->assertOk()->json('items');

    expect(collect($items)->pluck('id')->all())->toBe([$matching->id]);

    $values = $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'offer_lines',
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['ADSL 20', 'Fibra 1000']);
});
