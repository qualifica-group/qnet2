<?php

use App\Models\DocumentLayout;
use App\Models\Role;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-050 — 403 without the matching permission, on every endpoint
// ---------------------------------------------------------------------------

it('GET show: 403 without document-layouts.view (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/document-layouts/{$target->id}")->assertForbidden();
});

it('POST store: 403 without document-layouts.create, no row created (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $countBefore = DocumentLayout::count();

    $this->postJson('/api/document-layouts', [
        'name' => 'Nope', 'code' => 'nope', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertForbidden();

    expect(DocumentLayout::count())->toBe($countBefore);
});

it('PATCH update: 403 without document-layouts.update, no change persisted (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    $target = DocumentLayout::factory()->create(['description' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['description' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('document_layouts', ['id' => $target->id, 'description' => 'Untouched']);
});

it('DELETE destroy: 403 without document-layouts.delete, record still exists (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/document-layouts/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('document_layouts', ['id' => $target->id]);
});

it('GET for-select: 403 without document-layouts.viewAny (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/for-select?module=quotes')->assertForbidden();
});

it('GET variables: 403 without document-layouts.viewAny (AC-050)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/variables?module=quotes')->assertForbidden();
});

// NOTE: `GET /api/tables/document-layouts/columns` and
// `POST /api/exports/document-layouts` (AC-050's remaining two endpoints)
// are intentionally NOT covered here — they only exist once
// `App\Tables\DocumentLayoutsTableDefinition` is registered in
// `config/tables.php`, which is a DIFFERENT owner's file/artifact in this
// wave (backend.md/table framework split). Once that lands, both routes work
// "for free" off the generic table framework (spec context: "Export: nessun
// registro per-dominio... serve solo la permission document-layouts.export"),
// gated purely by the `document-layouts.viewAny`/`.export` permissions this
// file already proves exist and are enforced.

// ---------------------------------------------------------------------------
// AC-051 — permissions:sync creates the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 document-layouts.* permissions and no more (AC-051)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "document-layouts.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'like', 'document-layouts.%')->count())->toBe(8);
});

// ---------------------------------------------------------------------------
// AC-052 — navigation node gated by document-layouts.view
// ---------------------------------------------------------------------------

it('navigation: the document-layouts node only shows with document-layouts.view (AC-052)', function () {
    Permission::findOrCreate('document-layouts.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->not->toContain('document-layouts');

    $withView = User::factory()->create();
    $withView->givePermissionTo('document-layouts.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->toContain('document-layouts');
});

// ---------------------------------------------------------------------------
// AC-053 — 403 precedence over a field-level 422 (both plain fields and the
// config structural validator)
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422 (AC-053)', function () {
    $actor = documentLayoutUserWith([]);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['description' => 'blocked'])->assertForbidden();
});

it('a 403 (no create ability) takes precedence over an invalid config 422 (AC-053)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Bad Config', 'code' => 'bad_config', 'module' => 'quotes',
        'config' => ['version' => 2],
    ])->assertForbidden();
});

it('a 403 (no update ability) takes precedence over an invalid config 422 (AC-053)', function () {
    $actor = documentLayoutUserWith([]);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['config' => ['version' => 2]])->assertForbidden();
});

it('with document-layouts.update: an invalid config IS reported as a field-level 422 (config validator wiring)', function () {
    $actor = documentLayoutUserWith(['update']);
    $target = DocumentLayout::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['config' => ['version' => 2]])
        ->assertStatus(422)->assertJsonValidationErrors('config.version');
});

// ---------------------------------------------------------------------------
// AC-054 — role_field_permissions restricting a field 422s a real change but
// 200s a no-touch PATCH.
//
// NOTE: demonstrated on `is_active`, not `config` — `config` is declared
// `mandatory: true` in DocumentLayoutsAuthorization::fields() (field_permissions
// block), and a mandatory field's DB row is UNCONDITIONALLY bypassed by
// AbstractResourceAuthorization::fieldPermissions() (spec 0008, proven below
// by AC-055's own `config` case): a DB restriction on `config` can never
// produce the 422 this criterion describes, by design. The change-based gate
// itself (EnforcesFieldPermissions) is resource-agnostic, so `is_active`
// exercises the exact same mechanism AC-054 targets.
// ---------------------------------------------------------------------------

it('update: a DB row denying `is_active` 422s a change but 200s a description-only PATCH (AC-054)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("document-layouts.{$ability}");
    }

    $role = Role::create(['name' => 'document-layout-is-active-locked']);
    $role->givePermissionTo(['document-layouts.view', 'document-layouts.update']);
    $role->fieldPermissions()->create([
        'resource' => 'document-layouts',
        'field' => 'is_active',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = DocumentLayout::factory()->create(['name' => 'Original', 'description' => 'Kept', 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/document-layouts/{$target->id}", ['is_active' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_active');

    $this->patchJson("/api/document-layouts/{$target->id}", ['description' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Changed');
});

// ---------------------------------------------------------------------------
// AC-055 — mandatory fields (name/code/module/config) bypass a restrictive
// DB matrix on create
// ---------------------------------------------------------------------------

it('create: a restrictive DB row on `name` is ignored (mandatory bypass), write succeeds (AC-055)', function () {
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("document-layouts.{$ability}");
    }

    $role = Role::create(['name' => 'document-layout-name-locked']);
    $role->givePermissionTo(['document-layouts.view', 'document-layouts.create']);
    $role->fieldPermissions()->create([
        'resource' => 'document-layouts',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Bypassed', 'code' => 'bypassed', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bypassed');
});

it('create: a restrictive DB row on `config` is ignored (mandatory bypass), write succeeds (AC-055)', function () {
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("document-layouts.{$ability}");
    }

    $role = Role::create(['name' => 'document-layout-config-mandatory-locked']);
    $role->givePermissionTo(['document-layouts.view', 'document-layouts.create']);
    $role->fieldPermissions()->create([
        'resource' => 'document-layouts',
        'field' => 'config',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $this->postJson('/api/document-layouts', [
        'name' => 'Bypassed Config', 'code' => 'bypassed_config', 'module' => 'quotes', 'config' => DocumentLayoutFactory::minimalConfig(),
    ])->assertCreated();
});
