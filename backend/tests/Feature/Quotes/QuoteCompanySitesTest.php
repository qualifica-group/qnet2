<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Societa' / Societa' Sede / Sede operativa on a Quote (user directive
 * 2026-07-30): the three optional FKs, the sede operativa inherited from the
 * Opportunity on create (same snapshot rule as the 3 commercial roles, D-3),
 * the server-side site-belongs-to-company guard, and the three new grid
 * columns (projection, sort, set filter, distinct values).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteSitesUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteSitesUserWith(array $abilities): User
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

if (! function_exists('quoteSitesOperationalSite')) {
    /** An operational site whose primary address `line1` is known, so label/sort/filter are assertable. */
    function quoteSitesOperationalSite(string $line1): OperationalSite
    {
        $site = OperationalSite::factory()->create();

        Address::factory()
            ->primary()
            ->forCity(City::factory()->create())
            ->for($site, 'addressable')
            ->create(['line1' => $line1]);

        return $site;
    }
}

if (! function_exists('quoteSitesNewStatus')) {
    function quoteSitesNewStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
    }
}

// ---------------------------------------------------------------------------
// create — the 3 FKs and the sede operativa snapshot
// ---------------------------------------------------------------------------

it('persists the three submitted relations and exposes them on the resource', function () {
    quoteSitesNewStatus();
    $company = Company::factory()->create(['denomination' => 'Acme SpA']);
    $companySite = CompanySite::factory()->create(['company_id' => $company->id, 'name' => 'Sede Milano']);
    $operationalSite = quoteSitesOperationalSite('Via Roma 1');
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Con societa',
        'opportunity_id' => $opportunity->id,
        'company_id' => $company->id,
        'company_site_id' => $companySite->id,
        'operational_site_id' => $operationalSite->id,
    ])->assertCreated();

    $response->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.company.name', 'Acme SpA')
        ->assertJsonPath('data.company_site_id', $companySite->id)
        ->assertJsonPath('data.company_site.name', 'Sede Milano')
        ->assertJsonPath('data.operational_site_id', $operationalSite->id);

    expect($response->json('data.operational_site.label'))->toStartWith('Via Roma 1');
});

it('inherits the sede operativa from the opportunity when the key is not submitted', function () {
    quoteSitesNewStatus();
    $operationalSite = quoteSitesOperationalSite('Via Ereditata 2');
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $operationalSite->id]);
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Senza sede',
        'opportunity_id' => $opportunity->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.operational_site_id', $operationalSite->id);
});

it('lets an explicitly submitted sede operativa — even null — win over the opportunity', function () {
    quoteSitesNewStatus();
    $inherited = quoteSitesOperationalSite('Via Opportunita 3');
    $picked = quoteSitesOperationalSite('Via Scelta 4');
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $inherited->id]);
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Sede scelta',
        'opportunity_id' => $opportunity->id,
        'operational_site_id' => $picked->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.operational_site_id', $picked->id);

    $this->postJson('/api/quotes', [
        'title' => 'Sede azzerata',
        'opportunity_id' => $opportunity->id,
        'operational_site_id' => null,
    ])
        ->assertCreated()
        ->assertJsonPath('data.operational_site_id', null);
});

it('does not re-sync the sede operativa when the opportunity changes later', function () {
    quoteSitesNewStatus();
    $first = quoteSitesOperationalSite('Via Prima 5');
    $second = quoteSitesOperationalSite('Via Seconda 6');
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $first->id]);
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Snapshot',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->json('data.id');

    $opportunity->update(['operational_site_id' => $second->id]);

    expect(Quote::findOrFail($quoteId)->operational_site_id)->toBe($first->id);
});

// ---------------------------------------------------------------------------
// the site-belongs-to-company guard (server-side, not just the UI cascade)
// ---------------------------------------------------------------------------

it('rejects a company site that belongs to another company', function () {
    quoteSitesNewStatus();
    $company = Company::factory()->create();
    $otherSite = CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Sede estranea',
        'opportunity_id' => $opportunity->id,
        'company_id' => $company->id,
        'company_site_id' => $otherSite->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_site_id']);
});

it('rejects a company site submitted with no company at all', function () {
    quoteSitesNewStatus();
    $site = CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteSitesUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Sede orfana',
        'opportunity_id' => $opportunity->id,
        'company_site_id' => $site->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_site_id']);
});

it('validates a PATCH against the effective company: persisted when unsubmitted, submitted otherwise', function () {
    $company = Company::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $company->id]);
    $quote = Quote::factory()->create(['company_id' => $company->id]);
    Sanctum::actingAs(quoteSitesUserWith(['view', 'update']));

    // company_id absent from the payload -> the persisted one is the reference.
    $this->patchJson("/api/quotes/{$quote->id}", ['company_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.company_site_id', $site->id);

    // Clearing the company in the SAME request orphans the persisted site.
    $this->patchJson("/api/quotes/{$quote->id}", ['company_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_site_id']);
});

it('clears all three relations when they are submitted as null', function () {
    $company = Company::factory()->create();
    $quote = Quote::factory()->create([
        'company_id' => $company->id,
        'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
        'operational_site_id' => quoteSitesOperationalSite('Via Da Svuotare 7')->id,
    ]);
    Sanctum::actingAs(quoteSitesUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", [
        'company_id' => null,
        'company_site_id' => null,
        'operational_site_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.company', null)
        ->assertJsonPath('data.company_site', null)
        ->assertJsonPath('data.operational_site', null);
});

// ---------------------------------------------------------------------------
// the three grid columns
// ---------------------------------------------------------------------------

it('projects the three columns on the table rows', function () {
    $company = Company::factory()->create(['denomination' => 'Beta Srl']);
    $companySite = CompanySite::factory()->create(['company_id' => $company->id, 'name' => 'Sede Torino']);
    $operationalSite = quoteSitesOperationalSite('Via Griglia 8');
    Quote::factory()->create([
        'title' => 'Riga con sedi',
        'company_id' => $company->id,
        'company_site_id' => $companySite->id,
        'operational_site_id' => $operationalSite->id,
    ]);
    Sanctum::actingAs(quoteSitesUserWith(['viewAny']));

    $row = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->firstWhere('title', 'Riga con sedi');

    expect($row['company']['name'])->toBe('Beta Srl')
        ->and($row['company_site']['name'])->toBe('Sede Torino')
        ->and($row['operational_site']['label'])->toStartWith('Via Griglia 8');
});

it('sorts by company denomination and filters by company site name', function () {
    $alpha = Company::factory()->create(['denomination' => 'Alpha']);
    $zeta = Company::factory()->create(['denomination' => 'Zeta']);
    Quote::factory()->create(['title' => 'Zeta row', 'company_id' => $zeta->id]);
    Quote::factory()->create([
        'title' => 'Alpha row',
        'company_id' => $alpha->id,
        'company_site_id' => CompanySite::factory()->create(['company_id' => $alpha->id, 'name' => 'Sede Alpha'])->id,
    ]);
    Sanctum::actingAs(quoteSitesUserWith(['viewAny']));

    $sorted = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sort' => [['columnId' => 'company', 'direction' => 'asc']],
    ])->assertOk()->json('items');

    expect(collect($sorted)->pluck('title')->all())->toBe(['Alpha row', 'Zeta row']);

    $filtered = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['company_site' => ['filterType' => 'set', 'values' => ['Sede Alpha']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('title')->all())->toBe(['Alpha row']);
});

it('offers the operational site address as distinct values and filters on it', function () {
    $site = quoteSitesOperationalSite('Via Distinta 9');
    Quote::factory()->create(['title' => 'Con sede', 'operational_site_id' => $site->id]);
    Quote::factory()->create(['title' => 'Senza sede']);
    Sanctum::actingAs(quoteSitesUserWith(['viewAny']));

    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'operational_site'])
        ->assertOk()
        ->json('data.values');

    expect($values)->toBe(['Via Distinta 9']);

    $filtered = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['operational_site' => ['filterType' => 'set', 'values' => ['Via Distinta 9']]],
    ])->assertOk()->json('items');

    expect(collect($filtered)->pluck('title')->all())->toBe(['Con sede']);
});
