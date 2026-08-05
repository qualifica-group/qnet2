<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `operational_site_id` (spec 0056): a plain, optional FK on Opportunity —
 * AC-001..AC-007. Split out of OpportunityCrudTest (file-size limit,
 * engineering.md §6).
 */
uses(RefreshDatabase::class);

if (! function_exists('operationalSiteOpportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function operationalSiteOpportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('grantOperationalSitesViewAny')) {
    /**
     * The field-permission ceiling for `operational_site_id`
     * (OpportunitiesAuthorization) ALSO requires `operational-sites.viewAny`,
     * mirroring the table engine's own relation-column invariant
     * (ResolvesEditableColumns::mayPickRelationValue) — without it, submitting
     * the field 422s "field not editable" even for an actor who can otherwise
     * write the opportunity.
     */
    function grantOperationalSitesViewAny(User $actor): void
    {
        $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    }
}

if (! function_exists('operationalSiteMandatoryOpportunityFks')) {
    /**
     * @return array{registry_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function operationalSiteMandatoryOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            // User directive 2026-07-23: products_of_interest is mandatory too;
            // the product belongs to the row's OWN category, so this payload
            // never triggers a cross-category product-line addition.
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

it('create: with a valid operational_site_id -> 201, persisted and read back (AC-001)', function () {
    $actor = operationalSiteOpportunityUserWith(['create']);
    grantOperationalSitesViewAny($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(operationalSiteMandatoryOpportunityFks(), [
        'name' => 'Deal Gamma',
        'operational_site_id' => $site->id,
    ]))->assertCreated();

    $this->assertDatabaseHas('opportunities', ['id' => $response->json('data.id'), 'operational_site_id' => $site->id]);
    expect($response->json('data.operational_site.id'))->toBe($site->id);
});

it('create: without operational_site_id -> 201, the field is null (AC-002)', function () {
    $actor = operationalSiteOpportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(operationalSiteMandatoryOpportunityFks(), ['name' => 'Deal Delta']))
        ->assertCreated();

    $this->assertDatabaseHas('opportunities', ['id' => $response->json('data.id'), 'operational_site_id' => null]);
    expect($response->json('data.operational_site'))->toBeNull();
});

it('create: a non-existent operational_site_id -> 422 (AC-005)', function () {
    $actor = operationalSiteOpportunityUserWith(['create']);
    grantOperationalSitesViewAny($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(operationalSiteMandatoryOpportunityFks(), [
        'name' => 'Deal Epsilon',
        'operational_site_id' => 999999,
    ]))->assertStatus(422)->assertJsonValidationErrors('operational_site_id');
});

it('update: PATCH with a valid operational_site_id updates the field (AC-003)', function () {
    $actor = operationalSiteOpportunityUserWith(['update']);
    grantOperationalSitesViewAny($actor);
    $opportunity = Opportunity::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.operational_site.id', $site->id);

    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'operational_site_id' => $site->id]);
});

it('update: PATCH operational_site_id: null AZZERA the field (AC-004)', function () {
    $actor = operationalSiteOpportunityUserWith(['update']);
    grantOperationalSitesViewAny($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['operational_site_id' => null])
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', null)
        ->assertJsonPath('data.operational_site', null);

    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'operational_site_id' => null]);
});

it('update: a non-existent operational_site_id -> 422 (AC-005)', function () {
    $actor = operationalSiteOpportunityUserWith(['update']);
    grantOperationalSitesViewAny($actor);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['operational_site_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect($opportunity->fresh()->operational_site_id)->toBeNull();
});

it('show: operational_site label is composed "{line1} - {city}" (AC-006)', function () {
    $actor = operationalSiteOpportunityUserWith(['view']);
    $site = OperationalSite::factory()->withAddress()->create();
    $site->addresses()->first()->update(['line1' => 'Via Roma 1']);
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $label = $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->json('data.operational_site.label');

    expect($label)->toStartWith('Via Roma 1 - ');
});

it('show: a site without a city -> label is just line1 (AC-007)', function () {
    $actor = operationalSiteOpportunityUserWith(['view']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['is_primary' => true, 'line1' => 'Via Sola 5']);
    $opportunity = Opportunity::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.operational_site.label', 'Via Sola 5');
});

it('update: a role whose field-permission on operational_site_id is readonly -> 422, no write (AC-014)', function () {
    $role = Role::create(['name' => 'opportunity-site-locked']);
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }
    Permission::findOrCreate('operational-sites.viewAny');
    $role->givePermissionTo(['opportunities.viewAny', 'opportunities.update', 'operational-sites.viewAny']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'operational_site_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    $opportunity = Opportunity::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');

    expect($opportunity->fresh()->operational_site_id)->toBeNull();
});

it('show: opportunity without a site -> operational_site is null', function () {
    $actor = operationalSiteOpportunityUserWith(['view']);
    $opportunity = Opportunity::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.operational_site', null)
        ->assertJsonPath('data.operational_site_id', null);
});
