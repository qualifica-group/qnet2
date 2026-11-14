<?php

use App\Models\Note;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// D-13-bis — the write rules of a normal edit apply to the merge too.

/**
 * An actor allowed to merge registries whose role locks the given fields.
 *
 * @param  array<int, string>  $lockedFields
 */
function registryMergeActorLocking(array $lockedFields): User
{
    foreach (['view', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $role = Role::create(['name' => 'registry-merge-locked']);
    $role->givePermissionTo(['registries.view', 'registries.update', 'registries.delete']);

    foreach ($lockedFields as $field) {
        $role->fieldPermissions()->create(['resource' => 'registries', 'field' => $field, 'visible' => true, 'editable' => false, 'required' => false]);
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

it('refuses to change a field the actor cannot edit, and changes nothing', function () {
    Sanctum::actingAs(registryMergeActorLocking(['employee_count']));
    $survivor = mergeCompany('Acme Srl', ['employee_count' => 10]);
    $duplicate = mergeCompany('Acme S.r.l.', ['employee_count' => 20]);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'fields' => ['employee_count' => $duplicate->id],
    ])->assertStatus(422)->assertJsonValidationErrors('fields.employee_count');

    expect(Registry::query()->whereKey($duplicate->id)->exists())->toBeTrue()
        ->and($survivor->fresh()->employee_count)->toBe(10);
});

it('lets a locked field through when the merge keeps the survivor value', function () {
    Sanctum::actingAs(registryMergeActorLocking(['employee_count']));
    $survivor = mergeCompany('Acme Srl', ['employee_count' => 10]);
    $duplicate = mergeCompany('Acme S.r.l.', ['employee_count' => 20]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect($survivor->fresh()->employee_count)->toBe(10);
});

it('refuses to add contacts when the actor cannot edit them, unless they are discarded', function () {
    Sanctum::actingAs(registryMergeActorLocking(['personal_data.contacts']));
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $contact = mergeContact($duplicate, 'email', 'info@acme.test');
    $payload = ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]];

    $this->postJson('/api/registries/merge', $payload)->assertStatus(422)->assertJsonValidationErrors('discard.contacts');

    $this->postJson('/api/registries/merge', [...$payload, 'discard' => ['contacts' => [$contact->id]]])->assertOk();
});

it('refuses combined texts longer than the field limit', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl', ['general_notes' => str_repeat('a', 3000)]);
    $duplicate = mergeCompany('Acme S.r.l.', ['general_notes' => str_repeat('b', 3000)]);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'fields' => ['general_notes' => 'combine'],
    ])->assertStatus(422)->assertJsonValidationErrors('fields.general_notes');

    expect(Registry::query()->whereKey($duplicate->id)->exists())->toBeTrue();
});

it('moves the deleted notes of the duplicate too, still deleted', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $deleted = mergeNote($duplicate, 'Old note');
    $deleted->delete();

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    $moved = Note::withTrashed()->find($deleted->id);
    expect($moved->notable_id)->toBe($survivor->id)
        ->and($moved->trashed())->toBeTrue();
});

it('shows the supplier links that move with the registry', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    mergeReferent('Mario', 'Rossi', ['supplier_registry_id' => $duplicate->id]);

    $linked = collect($this->postJson('/api/registries/merge/preview', ['ids' => [$survivor->id, $duplicate->id]])
        ->assertOk()
        ->json('data.linked'));

    expect($linked->firstWhere('key', 'paid_agents')['counts'])->toBe([['record_id' => $duplicate->id, 'count' => 1]]);
});
