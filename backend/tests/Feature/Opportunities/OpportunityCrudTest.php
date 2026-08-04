<?php

use App\Models\BusinessFunction;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('mandatoryOpportunityFks')) {
    /**
     * The mandatory create payload beyond `name`: `registry_id` (D-4),
     * `opportunity_status_id` (spec 0043, D-3), `supervisor_id`, plus a valid
     * one-row `product_lines` collection (user directive 2026-07-17: at least
     * one row is required to create) and a one-product `products_of_interest`
     * collection FROM THAT ROW'S CATEGORY (user directive 2026-07-23: at least
     * one product is required too — from the row's own category, so the
     * mandatory payload never triggers OpportunityProductInterestWriter's
     * cross-category product-line addition). Each a freshly created row. Tests
     * asserting a specific `product_lines` payload merge this helper FIRST
     * so their own value overrides it. User directive 2026-07-17:
     * `company_id`/`company_site_id` are REMOVED entirely; `operational_site_id`
     * (spec 0056) is reintroduced but stays OPTIONAL, so it is still not part
     * of this mandatory payload.
     *
     * @return array{registry_id: int, opportunity_status_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function mandatoryOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'opportunity_status_id' => OpportunityStatus::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

// ---------------------------------------------------------------------------
// create (AC-012/AC-013/AC-014/AC-016/AC-017/AC-082 — mandatory fields are
// name + registry_id + opportunity_status_id (spec 0043, D-3) + supervisor_id +
// product_lines; company_id/company_site_id were removed by user directive
// 2026-07-17; operational_site_id (spec 0056) is reintroduced, optional)
// ---------------------------------------------------------------------------

it('create: with the mandatory fields only -> 201, every optional scalar null (AC-082)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    // product_lines/products_of_interest are to-many relations, not
    // `opportunities` columns: asserted on the response/pivot, never via
    // assertDatabaseHas.
    $scalarFks = Arr::except($fks, ['product_lines', 'products_of_interest']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', $fks)
        ->assertCreated();

    $opportunityId = $response->json('data.id');
    // Spec 0057, D-5: name is derived (OPP_{id}), never a client input.
    $this->assertDatabaseHas('opportunities', array_merge(['id' => $opportunityId, 'name' => 'OPP_'.$opportunityId], $scalarFks, [
        'referent_id' => null,
        'commercial_id' => null,
        'reporter_id' => null,
        'source_id' => null,
        'lead_id' => null,
    ]));
    expect($response->json('data.product_lines'))->toHaveCount(1);
});

it('create: 201, response shape matches the frozen contract', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $registry = Registry::find($fks['registry_id']);
    $registry->update(['name' => 'Acme Spa']);
    $referent = Referent::factory()->create(['name' => 'Ada Contact']);
    $supervisor = User::factory()->create(['name' => 'Sara Supervisor']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge($fks, [
        'referent_id' => $referent->id,
        'supervisor_id' => $supervisor->id,
        'start_date' => '2026-02-01',
        'estimated_value' => 12345.67,
        'expected_close_date' => '2026-06-01',
        'success_probability' => 60,
    ]))->assertCreated()
        ->assertJsonPath('data.registry', ['id' => $registry->id, 'name' => 'Acme Spa'])
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => 'Ada Contact'])
        ->assertJsonPath('data.supervisor', ['id' => $supervisor->id, 'name' => 'Sara Supervisor'])
        ->assertJsonPath('data.estimated_value', '12345.67')
        ->assertJsonPath('data.success_probability', 60)
        ->assertJsonPath('data.lead_id', null)
        ->assertJsonPath('data.lead', null)
        ->assertJsonPath('data.locked_fields', []);

    $opportunity = Opportunity::sole();
    // Spec 0057, D-5: name is derived (OPP_{id}), never the submitted value.
    expect($response->json('data.name'))->toBe('OPP_'.$opportunity->id);
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
    expect($opportunity->start_date->format('Y-m-d'))->toBe('2026-02-01');
    expect($opportunity->expected_close_date->format('Y-m-d'))->toBe('2026-06-01');
});

// Spec 0057, D-5: `name` is no longer a client input — it is always derived
// as `OPP_{id}`, so a create payload carrying no `name` at all now succeeds
// (requirement changed: this used to 422 on the missing field).
it('create: name is derived as OPP_{id}, ignoring any submitted name', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(['name' => 'Ignored'], mandatoryOpportunityFks()))
        ->assertCreated();

    $opportunity = Opportunity::sole();
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
    expect($response->json('data.name'))->toBe('OPP_'.$opportunity->id);
});

it('create: missing registry_id -> 422 on that field, no row created', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['registry_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Registry'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

// Directive 2026-07-21 (relaxing spec 0044): supervisor_id is now OPTIONAL on
// create — it derives from the lead's Operatore, which may be empty.
it('create: missing supervisor_id -> 201, opportunity created with null supervisor', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['supervisor_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Supervisor'], $fks))
        ->assertCreated();

    expect(Opportunity::count())->toBe(1);
    expect(Opportunity::sole()->supervisor_id)->toBeNull();
});

it('create: null supervisor_id -> 201, opportunity created with null supervisor', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $fks['supervisor_id'] = null;
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Null Supervisor'], $fks))
        ->assertCreated();

    expect(Opportunity::count())->toBe(1);
    expect(Opportunity::sole()->supervisor_id)->toBeNull();
});

it('create: 403 without opportunities.create, no row created (AC-012)', function () {
    $actor = opportunityUserWith([]);
    $fks = mandatoryOpportunityFks();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Nope'], $fks))
        ->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

it('create: a non-existent registry_id -> 422 (exists), not 500 (AC-017)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $fks['registry_id'] = 999999;
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Ghost registry'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: success_probability 101 or -1 -> 422; 0 and 100 accepted (AC-014)', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Too high', 'success_probability' => 101], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('success_probability');

    $this->postJson('/api/opportunities', array_merge(['name' => 'Too low', 'success_probability' => -1], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('success_probability');

    $this->postJson('/api/opportunities', array_merge(['name' => 'Zero', 'success_probability' => 0], mandatoryOpportunityFks()))
        ->assertCreated();

    $this->postJson('/api/opportunities', array_merge(['name' => 'Hundred', 'success_probability' => 100], mandatoryOpportunityFks()))
        ->assertCreated();
});

it('create: estimated_value negative -> 422 (AC-014)', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Negative value', 'estimated_value' => -1], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('estimated_value');
});

// ---------------------------------------------------------------------------
// create: manager_slots (AC-015)
// ---------------------------------------------------------------------------

// manager_slots tests: OpportunityManagerSlotsTest.php (file-size split).

// ---------------------------------------------------------------------------
// update (AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH with only estimated_value -> 200, only that field changes (AC-013)', function () {
    $actor = opportunityUserWith(['update']);
    $opportunity = Opportunity::factory()->create(['name' => 'Original name', 'estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['estimated_value' => 999.99])
        ->assertOk()
        ->assertJsonPath('data.estimated_value', '999.99');

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'name' => 'Original name',
        'registry_id' => $opportunity->registry_id,
        'estimated_value' => 999.99,
    ]);
});

it('update: PATCH may clear supervisor_id to null', function () {
    $actor = opportunityUserWith(['update']);
    $opportunity = Opportunity::factory()->create(['supervisor_id' => User::factory()]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['supervisor_id' => null])
        ->assertOk()
        ->assertJsonPath('data.supervisor_id', null);

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'supervisor_id' => null,
    ]);
});

// ---------------------------------------------------------------------------
// show/delete authz (AC-012)
// ---------------------------------------------------------------------------

it('show: 403 without opportunities.view', function () {
    $actor = opportunityUserWith([]);
    $target = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent opportunity', function () {
    $actor = opportunityUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunities/999999')->assertNotFound();
});

it('delete: 403 without opportunities.delete', function () {
    $actor = opportunityUserWith([]);
    $target = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$target->id}")->assertForbidden();
});

it('delete: 204, removes the opportunity and its manager pivot rows; the linked lead is untouched (AC-016)', function () {
    $actor = opportunityUserWith(['delete']);
    $lead = Lead::factory()->create();
    $target = Opportunity::factory()->create(['lead_id' => $lead->id]);
    $manager = User::factory()->create();
    $target->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('opportunities', ['id' => $target->id]);
    $this->assertDatabaseMissing('opportunity_user', ['opportunity_id' => $target->id]);
    $this->assertDatabaseHas('leads', ['id' => $lead->id]);
});

it('delete: 404 for a non-existent opportunity', function () {
    $actor = opportunityUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/opportunities/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// response shape sanity for the remaining relation summaries
// ---------------------------------------------------------------------------

it('exposes source/product_lines summaries', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Vendite']);
    $source = Source::factory()->create(['name' => 'Fiera']);
    $productCategory = ProductCategory::factory()->create(['name' => 'Servizi Cloud', 'business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge($fks, [
        'name' => 'Full relations',
        'source_id' => $source->id,
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $productCategory->id],
        ],
        // Overridden too: the helper's product belongs to the helper's own
        // category, which this payload no longer declares — it would add a
        // second product line.
        'products_of_interest' => [Product::factory()->create(['category_id' => $productCategory->id])->id],
    ]))->assertCreated()
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Fiera']);

    $productLines = $response->json('data.product_lines');
    expect($productLines)->toHaveCount(1);
    expect($productLines[0]['business_function'])->toBe(['id' => $businessFunction->id, 'name' => 'Vendite']);
    expect($productLines[0]['product_category'])->toBe(['id' => $productCategory->id, 'name' => 'Servizi Cloud']);
});
