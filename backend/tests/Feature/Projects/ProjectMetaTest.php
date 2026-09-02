<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('projectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-034 — GET /api/meta/projects: permissions.fields carries the 6 flags for
// every field declared in ProjectsAuthorization::fields()
// ---------------------------------------------------------------------------

it('403 without projects.viewAny', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/projects')->assertForbidden();
});

it('200: field catalogue matches ProjectsAuthorization::fields(), in order (AC-034)', function () {
    $actor = projectUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/projects')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe([
        'code', 'name', 'description', 'pipeline_status_id', 'product_lines',
        'country_id', 'state_id', 'province_id', 'city_id',
        'partner_id', 'operational_site_id', 'start_date', 'end_date', 'total_budget', 'target_lead',
    ]);

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create (AC-034)', function () {
    $actor = projectUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    // requirement changed (spec 0039, D-3): pipeline_status_id is no longer
    // mandatory — an omitted FK falls back to the system_key='new' status.
    $this->getJson('/api/meta/projects')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.pipeline_status_id.required', false);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/projects')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = projectUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/projects')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
