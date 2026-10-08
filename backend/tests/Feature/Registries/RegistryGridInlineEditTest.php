<?php

declare(strict_types=1);

use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0206: the registries grid edits its cells through the form's own
// UpdateRegistryRequest + RegistryService (RegistryCellWriter).

uses(RefreshDatabase::class);

function registryGridEditor(bool $canUpdate = true): User
{
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo($canUpdate ? ['registries.viewAny', 'registries.update'] : ['registries.viewAny']);

    return $actor;
}

function patchRegistryCell(Registry $registry, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/registries/rows/{$registry->id}", ['column' => $column, 'value' => $value]);
}

it('declares every column the form edits as a single field', function () {
    Sanctum::actingAs(registryGridEditor());

    $editable = collect($this->getJson('/api/tables/registries/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();

    expect($editable)->toBe(['agreement_status', 'commercial', 'is_supplier', 'managers', 'reporter', 'size_class', 'source', 'supervisor']);
});

it('saves scalar, enum and relation cells through the form path', function () {
    Sanctum::actingAs(registryGridEditor());
    $registry = Registry::factory()->create(['is_supplier' => false]);
    $source = Source::factory()->create();
    $commercial = Referent::factory()->create();
    $supervisor = User::factory()->create();

    patchRegistryCell($registry, 'is_supplier', true)->assertOk()->assertJsonPath('data.is_supplier', true);
    patchRegistryCell($registry, 'agreement_status', 'agreed')->assertOk()->assertJsonPath('data.agreement_status', 'agreed');
    patchRegistryCell($registry, 'size_class', 'small')->assertOk()->assertJsonPath('data.size_class', 'small');
    patchRegistryCell($registry, 'source', $source->id)->assertOk()->assertJsonPath('data.source.id', $source->id);
    patchRegistryCell($registry, 'commercial', $commercial->id)->assertOk()->assertJsonPath('data.commercial.id', $commercial->id);
    patchRegistryCell($registry, 'supervisor', $supervisor->id)->assertOk()->assertJsonPath('data.supervisor.id', $supervisor->id);
});

it('runs the service side effects: unflagging a supplier clears the qualified flag', function () {
    Sanctum::actingAs(registryGridEditor());
    $registry = Registry::factory()->create(['is_supplier' => true, 'is_qualified_supplier' => true]);

    patchRegistryCell($registry, 'is_supplier', false)->assertOk();

    expect($registry->fresh()->is_qualified_supplier)->toBeFalse();
});

it('refuses a value outside the form enum', function () {
    Sanctum::actingAs(registryGridEditor());
    $registry = Registry::factory()->create();

    patchRegistryCell($registry, 'agreement_status', 'unknown')->assertUnprocessable();
});

it('keeps the remaining managers in their slots when the team changes in-cell', function () {
    Sanctum::actingAs(registryGridEditor());
    $registry = Registry::factory()->create();
    [$first, $second, $third, $newcomer] = User::factory()->count(4)->create()->all();
    $registry->managers()->sync([$first->id => ['position' => 1], $second->id => ['position' => 2], $third->id => ['position' => 3]]);

    patchRegistryCell($registry, 'managers', [$first->id, $third->id])->assertOk();
    patchRegistryCell($registry, 'managers', [$first->id, $third->id, $newcomer->id])->assertOk();

    $positions = $registry->fresh()->managers->mapWithKeys(fn (User $user): array => [$user->id => (int) $user->pivot->position])->all();

    expect($positions)->toBe([$first->id => 1, $newcomer->id => 2, $third->id => 3]);
});

it('keeps the card-derived name read-only', function () {
    Sanctum::actingAs(registryGridEditor());
    $registry = Registry::factory()->create(['name' => 'Originale']);

    patchRegistryCell($registry, 'name', 'Tentativo')->assertUnprocessable();

    expect($registry->fresh()->name)->toBe('Originale');
});

it('forbids the write without registries.update', function () {
    Sanctum::actingAs(registryGridEditor(canUpdate: false));
    $registry = Registry::factory()->create(['is_supplier' => false]);

    patchRegistryCell($registry, 'is_supplier', true)->assertForbidden();

    expect($registry->fresh()->is_supplier)->toBeFalse();
});
