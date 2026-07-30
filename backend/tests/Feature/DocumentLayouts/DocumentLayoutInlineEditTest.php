<?php

use App\Models\DocumentLayout;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// PATCH /api/tables/document-layouts/rows/{row} (spec 0069, AC-075). The
// generic guard chain (TableCellUpdateService) covers authz/structural/value
// checks identically to every other domain; the ONLY thing specific to this
// domain is DocumentLayoutsTableDefinition::updateCell()'s override, which
// routes an `is_active` write through DocumentLayoutDefaultManager's D-7d
// invariant (a predefinito layout cannot be deactivated) BEFORE persisting —
// exactly the guard the regular PATCH /document-layouts/{id} endpoint runs.

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
// AC-075 — happy path, both directions
// ---------------------------------------------------------------------------

it('PATCH {column: is_active, value: false} on a NON-predefinito layout -> 200, persisted (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.is_active', false);

    expect($target->fresh()->is_active)->toBeFalse();
});

it('PATCH {value: true} on an inactive, non-predefinito layout reactivates it (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => false, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => true,
    ])->assertOk()->assertJsonPath('data.is_active', true);

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-075 — D-7d: a predefinito layout cannot be deactivated inline
// ---------------------------------------------------------------------------

it('PATCH {column: is_active, value: false} on a PREDEFINITO layout -> 422, no write (AC-075, D-7d)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('is_active');

    expect($target->fresh()->is_active)->toBeTrue();
});

it('PATCH {column: is_active, value: true} on a PREDEFINITO already-active layout is a no-op success (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => true,
    ])->assertOk()->assertJsonPath('data.is_active', true);

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-075 — column allow-list: is_default is never editable, nor any other column
// ---------------------------------------------------------------------------

it('PATCH column=is_default -> 422, no write, even on a non-predefinito layout (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_default', 'value' => true,
    ])->assertStatus(422);

    expect($target->fresh()->is_default)->toBeFalse();
});

it('PATCH column=name (real, not editable) -> 422, no write (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'name', 'value' => 'Hacked',
    ])->assertStatus(422);

    expect($target->fresh()->name)->toBe('Untouched');
});

// ---------------------------------------------------------------------------
// AC-075 — value validation: non-boolean
// ---------------------------------------------------------------------------

it('PATCH value="maybe" (not boolean) -> 422, no write (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => 'maybe',
    ])->assertStatus(422);

    expect($target->fresh()->is_active)->toBeTrue();
});

it('PATCH without the value key -> 422 (is_active is not nullable) (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active',
    ])->assertStatus(422);

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-075 — authorization: base ability + field permission
// ---------------------------------------------------------------------------

it('PATCH without document-layouts.update -> 403, no write (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('PATCH with a DB row denying is_active -> 403, no write (AC-075)', function () {
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

    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-075 — row not found
// ---------------------------------------------------------------------------

it('PATCH on a non-existent row -> 404 (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/tables/document-layouts/rows/999999', [
        'column' => 'is_active', 'value' => false,
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// Audit: the override does not bypass the usual activity-log lifecycle
// ---------------------------------------------------------------------------

it('a successful PATCH writes an activity-log entry (log name document_layouts) for is_active (AC-075)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'update']);
    $target = DocumentLayout::factory()->create(['is_active' => true, 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/document-layouts/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'document_layouts')
        ->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($actor->id);
    expect($activity->properties->get('attributes'))->toHaveKey('is_active', false);
});
