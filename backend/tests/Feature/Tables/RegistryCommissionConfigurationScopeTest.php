<?php

use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\ExportRun;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0204: the `commission-configurations` grid scoped to one Anagrafica
 * by `registryId` lists only the rules whose recipient IS that registry
 * (morph alias `registry`), never a referent/user sharing the same id. AC-001.
 */
uses(RefreshDatabase::class);

function registryCommissionScopeActor(array $abilities = ['viewAny']): User
{
    foreach (['viewAny', 'view', 'export'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(array_map(static fn (string $a): string => "commission-configurations.{$a}", $abilities));

    return $actor;
}

/**
 * A supplier with one personal rule, another supplier with one, a referent
 * rule whose recipient id collides with the first supplier's id, and a plain
 * role-wide rule.
 *
 * @return array{supplier: Registry, ruleA: CommissionConfiguration}
 */
function registryCommissionScopeFixture(): array
{
    $supplier = Registry::factory()->create(['is_supplier' => true]);
    $other = Registry::factory()->create(['is_supplier' => true]);

    $ruleA = CommissionConfiguration::factory()
        ->forRecipient('registry', $supplier->id)
        ->create(['name' => 'Supplier A rule', 'recipient_role' => CommissionRecipientRole::Supplier]);
    CommissionConfiguration::factory()
        ->forRecipient('registry', $other->id)
        ->create(['name' => 'Supplier B rule', 'recipient_role' => CommissionRecipientRole::Supplier]);
    CommissionConfiguration::factory()
        ->forRecipient('referent', $supplier->id)
        ->create(['name' => 'Colliding referent rule']);
    CommissionConfiguration::factory()->create(['name' => 'Role-wide rule']);

    return ['supplier' => $supplier, 'ruleA' => $ruleA];
}

it('rows with registryId return only the rules whose recipient is that registry', function () {
    $fixture = registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor());

    $response = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'registryId' => $fixture['supplier']->id,
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($fixture['ruleA']->id);
});

it('rows without registryId return every rule, unchanged', function () {
    registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonPath('pagination.total', 4);
});

it('values are scoped to the registry', function () {
    $fixture = registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/values', [
        'columnId' => 'name',
        'registryId' => $fixture['supplier']->id,
    ])->assertOk()->assertJsonPath('data.values', ['Supplier A rule']);
});

it('columns accepts registry_id without changing the shape', function () {
    $fixture = registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor());

    $plain = $this->getJson('/api/tables/commission-configurations/columns')->assertOk()->json();
    $scoped = $this->getJson('/api/tables/commission-configurations/columns?registry_id='.$fixture['supplier']->id)
        ->assertOk()->json();

    expect($scoped)->toBe($plain);
});

it('without viewAny the endpoint stays 403 even with registryId', function () {
    $fixture = registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor(['view']));

    $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'registryId' => $fixture['supplier']->id,
    ])->assertForbidden();
});

it('export is scoped to the registry', function () {
    Storage::fake('local');
    $fixture = registryCommissionScopeFixture();
    Sanctum::actingAs(registryCommissionScopeActor(['viewAny', 'export']));

    $response = $this->postJson('/api/exports/commission-configurations', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
        'registryId' => $fixture['supplier']->id,
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state['registryId'])->toBe($fixture['supplier']->id)
        ->and($run->fresh()->row_count)->toBe(1);
});
