<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-050 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/company-sites/for-select')->assertUnauthorized();
});

it('allows actors without company-sites.viewAny (200 — ADR 0011 amended)', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/for-select')->assertOk();
});

it('allows actors with company-sites.viewAny (200) and returns the paginated envelope', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    CompanySite::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// AC-050 — item shape
// ---------------------------------------------------------------------------

it('maps a company site to { id, label: name, subtitle: company denomination }', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $company = Company::factory()->create(['denomination' => 'Acme Corp']);
    $target = CompanySite::factory()->create(['name' => 'Milano HQ', 'company_id' => $company->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/company-sites/for-select?search=Milano HQ')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray([
        'id' => $target->id,
        'label' => 'Milano HQ',
        'subtitle' => 'Acme Corp',
    ])->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle']);
});

it('omits subtitle when the site has no company', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $target = CompanySite::factory()->create(['name' => 'Unlinked Site', 'company_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/company-sites/for-select?search=Unlinked Site')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

// ---------------------------------------------------------------------------
// AC-050 — search
// ---------------------------------------------------------------------------

it('searches by name', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $match = CompanySite::factory()->create(['name' => 'Alphonse Target']);
    CompanySite::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/company-sites/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-050 — company_id scope (spec 0040 BR-4)
// ---------------------------------------------------------------------------

it('company_id restricts the list to sites of that company', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $siteA = CompanySite::factory()->create(['company_id' => $companyA->id]);
    CompanySite::factory()->create(['company_id' => $companyB->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/company-sites/for-select?company_id={$companyA->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($siteA->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('422 when company_id does not reference an existing company', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/for-select?company_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('company_id');
});

it('without company_id returns sites of every company', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    CompanySite::factory()->create(['company_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/company-sites/for-select')->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-050 — ids[] hydration (bypasses both search and company_id)
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search/company_id and does NOT inflate total', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $companyA = Company::factory()->create();
    $searchMatch = CompanySite::factory()->create(['name' => 'Zephyr Searchable', 'company_id' => $companyA->id]);
    $selected = CompanySite::factory()->create(['name' => 'Quentin Selected', 'company_id' => Company::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/company-sites/for-select?search=Zephyr&company_id={$companyA->id}&ids[]={$selected->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-050 — validation bounds + route order
// ---------------------------------------------------------------------------

it('rejects a limit above 100 (422)', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

it('resolves the literal for-select segment BEFORE the {companySite} wildcard', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    // A non-numeric "id" would 404/fail model binding if the wildcard route
    // matched first; the literal for-select controller must win.
    $this->getJson('/api/company-sites/for-select')->assertOk();
});
