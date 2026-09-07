<?php

use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// `manager_slots` (spec 0020, "Gestori"): the ordered, gap-aware pivot write
// path shared by every domain that carries "Gestori Account". Split out of
// RegistryCrudTest (file-size limit, engineering.md §6). Cap raised 4 -> 12
// by spec 0080 amendment A1 (App\Support\ManagerPositions).

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('minimalRegistryProfilePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function minimalRegistryProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            // An anagrafica must carry a phone number at creation (user
            // directive 2026-09-07): a payload without one is no longer a
            // valid create, so the minimal one holds it.
            'contacts' => [['type' => 'phone', 'value' => '+39 02 1112223', 'is_primary' => true]],
        ], $overrides);
    }
}

it('create: 422 when manager_slots has more than 12 filled slots (spec 0080 amendment A1)', function () {
    $actor = registryUserWith(['create']);
    $managers = User::factory()->count(13)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'manager_slots' => $managers->pluck('id')->all(),
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertStatus(422)->assertJsonValidationErrors('manager_slots');
});

it('create: accepts up to 12 filled manager_slots (spec 0080 amendment A1, AC-056)', function () {
    $actor = registryUserWith(['create']);
    $managers = User::factory()->count(12)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/registries', [
        'is_supplier' => false,
        'manager_slots' => $managers->pluck('id')->all(),
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated();

    expect($response->json('data.manager_slots'))->toHaveCount(12)
        // AC-056: RegistryResource never carries a `manager_labels` key —
        // the Registry path stays on the frontend's default denominations,
        // untouched by spec 0080.
        ->and($response->json('data'))->not->toHaveKey('manager_labels');
});

it('update: PATCH manager_slots attaches new managers (authoritative sync, not additive)', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->create();
    $oldManager = User::factory()->create();
    $registry->managers()->sync([$oldManager->id => ['position' => 1]]);
    $newManager = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['manager_slots' => [$newManager->id]])
        ->assertOk()
        ->assertJsonPath('data.manager_ids', [$newManager->id]);

    expect($registry->fresh()->managers->pluck('id')->all())->toBe([$newManager->id]);
});

it('show: managers expose their static G.A. position and gaps stay empty', function () {
    $actor = registryUserWith(['view']);
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $registry = Registry::factory()->create();
    // Occupy G.A.1 and G.A.3, leaving G.A.2 an empty slot (gap).
    $registry->managers()->sync([
        $ga1->id => ['position' => 1],
        $ga3->id => ['position' => 3],
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('data.managers.0.position', 1)
        ->assertJsonPath('data.managers.1.position', 3)
        ->assertJsonPath('data.manager_slots', [$ga1->id, null, $ga3->id]);
});

it('update: PATCH manager_slots persists gaps (empty G.A. slots)', function () {
    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->create();
    [$a, $b] = User::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    // Fill G.A.1 and G.A.3, G.A.2 empty.
    $this->patchJson("/api/registries/{$registry->id}", ['manager_slots' => [$a->id, null, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.manager_slots', [$a->id, null, $b->id]);

    expect($registry->fresh()->managers()->wherePivot('position', 2)->exists())->toBeFalse();
});

it('AC-058: the max-managers validation message reports the CURRENT cap (12), not a hand-written "4"', function () {
    $actor = registryUserWith(['create']);
    $managers = User::factory()->count(13)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/registries', [
        'is_supplier' => false,
        'manager_slots' => $managers->pluck('id')->all(),
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertStatus(422);

    $message = $response->json('errors.manager_slots.0');
    expect($message)->toContain('12')->not->toContain('at most 4');
});
