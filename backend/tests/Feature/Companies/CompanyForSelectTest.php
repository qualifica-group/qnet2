<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanyAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-009 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/companies/for-select')->assertUnauthorized();
});

it('allows actors without companies.viewAny (200 — ADR 0011 amended)', function () {
    $actor = userWithCompanyAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/companies/for-select')->assertOk();
});

it('allows actors with companies.viewAny (200) and returns the paginated envelope', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    Company::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/companies/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// AC-009 — item shape
// ---------------------------------------------------------------------------

it('maps a company to { id, label: denomination, subtitle: vat_number }', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $target = Company::factory()->create(['denomination' => 'Acme Corp', 'vat_number' => 'IT12345678901']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/companies/for-select?search=Acme Corp')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray([
        'id' => $target->id,
        'label' => 'Acme Corp',
        'subtitle' => 'IT12345678901',
    ])->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle']);
});

it('omits subtitle when vat_number is absent', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $target = Company::factory()->create(['denomination' => 'No Vat Ltd', 'vat_number' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/companies/for-select?search=No Vat Ltd')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

// ---------------------------------------------------------------------------
// AC-009 — search
// ---------------------------------------------------------------------------

it('searches by denomination', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $match = Company::factory()->create(['denomination' => 'Alphonse Target']);
    Company::factory()->create(['denomination' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/companies/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-009 — ids[] hydration
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $searchMatch = Company::factory()->create(['denomination' => 'Zephyr Searchable']);
    $selected = Company::factory()->create(['denomination' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/companies/for-select?search=Zephyr&ids[]={$selected->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-009 — validation bounds
// ---------------------------------------------------------------------------

it('rejects a limit above 100 (422)', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/companies/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});
