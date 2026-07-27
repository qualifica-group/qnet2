<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityMetaUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityMetaUserWith(array $abilities): User
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

const OPPORTUNITY_FIELD_KEYS = [
    'registry_id',
    'referent_id', 'commercial_id', 'reporter_id', 'supervisor_id',
    'source_id', 'operational_site_id', 'opportunity_status_id', 'product_lines', 'products_of_interest', 'manager_slots', 'start_date',
    'estimated_value', 'expected_close_date', 'success_probability',
    // User directive 2026-07-27: "Note generali", inherited from the lead's
    // own notes at conversion.
    'general_notes',
];

// ---------------------------------------------------------------------------
// AC-031 — GET /api/meta/opportunities: the 16 fields (amendment rev.3:
// business_function_id/product_category_id merged into product_lines; user
// directive 2026-07-17: company_id/company_site_id/operational_site_id
// REMOVED entirely — but spec 0056, 2026-07-23, REINSTATES operational_site_id
// alone, as an optional FK: company_id/company_site_id stay removed;
// spec 0043, D-3: opportunity_status_id ADDED, mandatory;
// user directive 2026-07-22: products_of_interest ADDED, optional;
// spec 0057, D-5: name REMOVED entirely — no longer permissionable, it is
// derived server-side, never a client input), lead_id absent
// ---------------------------------------------------------------------------

it('403 without opportunities.viewAny', function () {
    $actor = opportunityMetaUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')->assertForbidden();
});

it('200: field catalogue has the 16 contract fields, in order, lead_id absent (AC-031)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(OPPORTUNITY_FIELD_KEYS)
        ->and($keys)->toHaveCount(16);
    expect($keys)->not->toContain('lead_id');
    // Spec 0057, D-5: name is structural/immutable, not even readonly-visible.
    expect($keys)->not->toContain('name');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create, the mandatory fields required (AC-031/AC-083)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.registry_id.required', true)
        ->assertJsonPath('permissions.fields.product_lines.required', true)
        ->assertJsonPath('permissions.fields.estimated_value.required', false);
});

it('200: the mandatory fields are not restrictable by field-permissions (AC-083)', function () {
    $role = Role::create(['name' => 'opportunity-registry-locked']);
    foreach (['viewAny', 'create'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }
    $role->givePermissionTo(['opportunities.viewAny', 'opportunities.create']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'registry_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    // A DB row attempting to narrow a mandatory field is ignored (spec 0008
    // ceiling bypass for mandatory fields) — registry_id stays editable+required.
    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.registry_id.editable', true)
        ->assertJsonPath('permissions.fields.registry_id.required', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = opportunityMetaUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.registry_id.editable', false)
        ->assertJsonPath('permissions.fields.registry_id.readonly', true);
});

// ---------------------------------------------------------------------------
// AC-013 (spec 0056) — operational_site_id's ceiling ALSO requires
// operational-sites.viewAny: an actor with opportunities.update but WITHOUT
// it gets the field READ-ONLY, never finta-editabile.
// ---------------------------------------------------------------------------

it('permissions.fields.operational_site_id is editable when the actor may create AND holds operational-sites.viewAny (AC-013)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Permission::findOrCreate('operational-sites.viewAny');
    $actor->givePermissionTo('operational-sites.viewAny');
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.operational_site_id.editable', true)
        ->assertJsonPath('permissions.fields.operational_site_id.required', false);
});

it('permissions.fields.operational_site_id is READ-ONLY for an actor with opportunities.create but WITHOUT operational-sites.viewAny (AC-013)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.operational_site_id.editable', false)
        ->assertJsonPath('permissions.fields.operational_site_id.readonly', true);
});

// ---------------------------------------------------------------------------
// AC-033 — the resource surfaces in the Role matrix's field catalogue too
// ---------------------------------------------------------------------------

it('GET /api/authorization/fields includes opportunities with its 16 fields (AC-033)', function () {
    foreach (['viewAny', 'create'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();

    $resources = collect($response->json('data.resources'))->pluck('resource');
    expect($resources)->toContain('opportunities');

    $entry = collect($response->json('data.resources'))->firstWhere('resource', 'opportunities');
    $keys = collect($entry['fields'])->pluck('key')->all();
    expect($keys)->toBe(OPPORTUNITY_FIELD_KEYS);
});
