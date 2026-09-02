<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-031/AC-034 — GET /api/meta/leads: the 8 fields (spec 0033 adds
// `extra_fields`, spec 0094 adds `products_of_interest`), permissions.fields shape
// ---------------------------------------------------------------------------

it('403 without leads.viewAny', function () {
    $actor = leadUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/leads')->assertForbidden();
});

it('200: field catalogue matches LeadsAuthorization::fields(), in order (AC-031)', function () {
    $actor = leadUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/leads')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe([
        'registry_id', 'campaign_id', 'operational_site_id', 'source_id', 'operator_id', 'notes', 'extra_fields', 'products_of_interest',
    ]);

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create, mandatory fields required (AC-031)', function () {
    $actor = leadUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/leads')
        ->assertOk()
        ->assertJsonPath('permissions.fields.registry_id.editable', true)
        ->assertJsonPath('permissions.fields.registry_id.required', true)
        ->assertJsonPath('permissions.fields.campaign_id.required', true)
        ->assertJsonPath('permissions.fields.notes.required', false);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = leadUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/leads')
        ->assertOk()
        ->assertJsonPath('permissions.fields.registry_id.editable', false)
        ->assertJsonPath('permissions.fields.registry_id.readonly', true);
});

// ---------------------------------------------------------------------------
// AC-034 — the resource surfaces in the Role matrix's field catalogue too
// ---------------------------------------------------------------------------

it('GET /api/authorization/fields includes leads with its 8 fields (AC-034)', function () {
    foreach (['viewAny', 'create'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();

    $resources = collect($response->json('data.resources'))->pluck('resource');
    expect($resources)->toContain('leads');

    $leadsEntry = collect($response->json('data.resources'))->firstWhere('resource', 'leads');
    $keys = collect($leadsEntry['fields'])->pluck('key')->all();
    expect($keys)->toBe([
        'registry_id', 'campaign_id', 'operational_site_id', 'source_id', 'operator_id', 'notes', 'extra_fields', 'products_of_interest',
    ]);
});
