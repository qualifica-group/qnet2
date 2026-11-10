<?php

use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referent-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referent-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/referent-types/{referentType} (AC-003)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = referentTypeUserWith(['view']);
    $target = ReferentType::factory()->create(['name' => 'Commercial']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referent-types/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Commercial');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without referent-types.view', function () {
    $actor = referentTypeUserWith([]);
    $target = ReferentType::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/referent-types/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent type', function () {
    $actor = referentTypeUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referent-types/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/referent-types (AC-004)
// ---------------------------------------------------------------------------

it('create: 201 + persists', function () {
    $actor = referentTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referent-types', ['name' => 'Technical'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Technical');

    $this->assertDatabaseHas('referent_types', ['name' => 'Technical']);
});

it('create: 403 without referent-types.create', function () {
    $actor = referentTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referent-types', ['name' => 'Nope'])->assertForbidden();
});

it('create: 422 when name is missing', function () {
    $actor = referentTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referent-types', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when name exceeds 191 characters', function () {
    $actor = referentTypeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referent-types', ['name' => str_repeat('a', 192)])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/referent-types/{referentType} (AC-005)
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the type', function () {
    $actor = referentTypeUserWith(['update']);
    $target = ReferentType::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referent-types/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('referent_types', ['id' => $target->id, 'name' => 'After']);
});

it('update: 403 without referent-types.update', function () {
    $actor = referentTypeUserWith([]);
    $target = ReferentType::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referent-types/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent type', function () {
    $actor = referentTypeUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/referent-types/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/referent-types/{referentType} (AC-005)
// ---------------------------------------------------------------------------

it('delete: 204 removes an unused type', function () {
    $actor = referentTypeUserWith(['delete']);
    $target = ReferentType::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referent-types/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('referent_types', ['id' => $target->id]);
});

// REQUIREMENT CHANGED (spec 0384): a type used by a referent is no longer deleted with the link nulled: it is refused (409).
it('delete: 409 when a referent uses the type, the link stays', function () {
    $actor = referentTypeUserWith(['delete']);
    $target = ReferentType::factory()->create();
    $referent = Referent::factory()->create(['referent_type_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referent-types/{$target->id}")->assertStatus(409)->assertJsonPath('success', false);

    $this->assertDatabaseHas('referent_types', ['id' => $target->id]);
    expect($referent->fresh()->referent_type_id)->toBe($target->id);
});

it('delete: 403 without referent-types.delete', function () {
    $actor = referentTypeUserWith([]);
    $target = ReferentType::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referent-types/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent type', function () {
    $actor = referentTypeUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/referent-types/999999')->assertNotFound();
});
