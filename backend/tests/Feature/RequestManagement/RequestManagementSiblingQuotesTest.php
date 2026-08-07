<?php

use App\Enums\QuoteLineType;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * AC-006/AC-015: two Offerte (Quote) of the SAME Opportunity are the most
 * novel — and riskiest — shape spec 0086 introduces (D-1: a grid row is
 * always a single Quote, never the Opportunity). No fixture anywhere else in
 * this migration exercised TWO Quote rows on ONE Opportunity at once, so
 * neither the shared/independent field split (AC-006) nor a write's
 * propagation across siblings (AC-015) was ever proven. This file is that
 * proof, on a single shared fixture.
 */
uses(RefreshDatabase::class);

if (! function_exists('siblingQuotesActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function siblingQuotesActor(array $abilities = ['viewAny', 'viewAll', 'update']): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('siblingQuotes')) {
    /**
     * ONE Opportunity carrying two Offerte, deliberately diverging on every
     * per-Quote dimension the row projects — the shared fields (source,
     * product_categories, next_callback_at, client anagraphic) come from the
     * SAME Opportunity by construction; only what is explicitly set below
     * (supervisor/site/lines) differs between the two.
     *
     * @return array{opportunity: Opportunity, first: Quote, second: Quote}
     */
    function siblingQuotes(): array
    {
        $registry = Registry::factory()->withPersonalData()->create();
        $registry->personalData->update(['first_name' => 'Mario', 'last_name' => 'Rossi']);
        $category = ProductCategory::factory()->create();
        $opportunity = Opportunity::factory()->create([
            'registry_id' => $registry->id,
            'source_id' => Source::factory()->create()->id,
            'next_callback_at' => now()->addDay(),
        ]);
        OpportunityProductLine::factory()->for($opportunity)->create([
            'product_category_id' => $category->id,
        ]);

        $firstSite = OperationalSite::factory()->withAddress()->create();
        $secondSite = OperationalSite::factory()->withAddress()->create();

        $first = Quote::factory()->for($opportunity)->create([
            'supervisor_id' => User::factory()->create()->id,
            'operational_site_id' => $firstSite->id,
        ]);
        $second = Quote::factory()->for($opportunity)->create([
            'supervisor_id' => User::factory()->create()->id,
            'operational_site_id' => $secondSite->id,
        ]);
        // `is_transferred` is not fillable (D-6 system flag): set directly,
        // mirroring RequestContactTransferGridTest's own precedent.
        $second->is_transferred = true;
        $second->save();

        QuoteLine::factory()->create([
            'quote_id' => $first->id,
            'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            'line_type' => QuoteLineType::Revenue,
        ]);
        QuoteLine::factory()->create([
            'quote_id' => $second->id,
            'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            'line_type' => QuoteLineType::Revenue,
        ]);

        return ['opportunity' => $opportunity, 'first' => $first, 'second' => $second];
    }
}

it('AC-006: two sibling offers share the opportunity-level fields and diverge on their own', function () {
    ['first' => $first, 'second' => $second] = siblingQuotes();
    Sanctum::actingAs(siblingQuotesActor());

    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    $firstRow = $items->firstWhere('id', $first->id);
    $secondRow = $items->firstWhere('id', $second->id);

    expect($firstRow)->not->toBeNull()->and($secondRow)->not->toBeNull();

    // Shared: read through the SAME Opportunity, byte-identical on both rows.
    expect($firstRow['source'])->toBe($secondRow['source'])
        ->and($firstRow['product_categories'])->toBe($secondRow['product_categories'])
        ->and($firstRow['next_callback_at'])->toBe($secondRow['next_callback_at'])
        ->and($firstRow['first_name'])->toBe($secondRow['first_name'])
        ->and($firstRow['last_name'])->toBe($secondRow['last_name']);

    // Independent: each Quote's own columns, never mirrored across siblings.
    expect($firstRow['operator_ga2']['id'])->not->toBe($secondRow['operator_ga2']['id'])
        ->and($firstRow['operational_site']['id'])->not->toBe($secondRow['operational_site']['id'])
        ->and($firstRow['is_transferred'])->toBeFalse()
        ->and($secondRow['is_transferred'])->toBeTrue()
        ->and(collect($firstRow['offer_lines'])->pluck('id')->all())
        ->not->toBe(collect($secondRow['offer_lines'])->pluck('id')->all());
});

it('AC-015: an inline source edit on one offer propagates to its sibling', function () {
    ['first' => $first, 'second' => $second] = siblingQuotes();
    $newSource = Source::factory()->create();
    Sanctum::actingAs(siblingQuotesActor(['viewAny', 'viewAll', 'view', 'update', 'updateSource']));

    $this->patchJson("/api/request-management/{$first->id}", [
        'source_id' => $newSource->id,
    ])->assertOk()->assertJsonPath('data.source.id', $newSource->id);

    $siblingPanel = $this->getJson("/api/request-management/{$second->id}")->assertOk();
    expect($siblingPanel->json('data.source.id'))->toBe($newSource->id);

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($rows->firstWhere('id', $second->id)['source']['id'])->toBe($newSource->id);
});
