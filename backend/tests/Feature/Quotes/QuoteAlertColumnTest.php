<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `alert` column on the `quotes` table (spec 0102, D-4): calculated at
 * read time from the Offerta's own REVENUE lines, never persisted — mirrors
 * ContractsTableDefinition's own `alert` (see QuotesTableDefinition
 * docblock). AC-030..035.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteAlertUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteAlertUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

it('AC-030: an offer with zero REVENUE lines exposes alert = missing_offer_lines', function () {
    $actor = quoteAlertUserWith(['viewAny']);
    $quote = Quote::factory()->create(['title' => 'Offerta senza righe']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['alert'])->toBe('missing_offer_lines');
});

it('AC-031: an offer with at least one REVENUE line exposes alert = null', function () {
    $actor = quoteAlertUserWith(['viewAny']);
    $quote = Quote::factory()->create(['title' => 'Offerta con riga']);
    QuoteLine::factory()->for($quote)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['alert'])->toBeNull();
});

it('AC-032: an offer with only COST lines still exposes alert = missing_offer_lines', function () {
    $actor = quoteAlertUserWith(['viewAny']);
    $quote = Quote::factory()->create(['title' => 'Offerta solo costi']);
    QuoteLine::factory()->for($quote)->cost()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row['alert'])->toBe('missing_offer_lines');
});

it('AC-033: the set filter alert=missing_offer_lines returns only offers with zero REVENUE lines, total coherent', function () {
    $actor = quoteAlertUserWith(['viewAny']);
    $withoutLines = Quote::factory()->create(['title' => 'Senza righe']);
    $withCostOnly = Quote::factory()->create(['title' => 'Solo costi']);
    QuoteLine::factory()->for($withCostOnly)->cost()->create();
    $withRevenue = Quote::factory()->create(['title' => 'Con riga']);
    QuoteLine::factory()->for($withRevenue)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['alert' => ['filterType' => 'set', 'values' => ['missing_offer_lines']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($withoutLines->id)
        ->and($ids)->toContain($withCostOnly->id)
        ->and($ids)->not->toContain($withRevenue->id)
        ->and($response->json('pagination.total'))->toBe($ids->count());
});

it('AC-034: the alert column does not add an N+1 query as the number of offers grows', function () {
    $actor = quoteAlertUserWith(['viewAny']);

    $small = Opportunity::factory()->create();
    $smallQuote = Quote::factory()->for($small)->create();
    QuoteLine::factory()->for($smallQuote)->create();

    $large = Opportunity::factory()->create();
    foreach (range(1, 10) as $i) {
        $quote = Quote::factory()->for($large)->create();
        QuoteLine::factory()->for($quote)->create();
    }

    Sanctum::actingAs($actor);

    // Warm permission cache before measuring, mirroring
    // OpportunityQuotesCountTest's own precedent.
    $actor->can('quotes.viewAny');
    $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25]);

    DB::enableQueryLog();
    $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 1])->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

it('AC-035: alert is appended in coda to the column catalogue, not sortable, and no existing column moves', function () {
    $actor = quoteAlertUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'));
    $alert = $columns->firstWhere('id', 'alert');

    expect($columns->last()['id'])->toBe('alert')
        ->and($alert)->not->toBeNull()
        ->and($alert['sortable'])->toBeFalse()
        ->and($alert['filterable'])->toBeTrue()
        ->and($alert['filterType'])->toBe('set')
        ->and($alert['options'])->toBe(['missing_offer_lines']);
});
