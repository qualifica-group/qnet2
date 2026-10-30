<?php

use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\ExportRun;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0336: the `commission-configurations` grid scoped to one Referente by
 * `referentId` lists only the rules whose recipient IS that referent (morph
 * alias `referent`), never a registry/user sharing the same id. AC-001.
 */
uses(RefreshDatabase::class);

function referentCommissionScopeActor(array $abilities = ['viewAny']): User
{
    foreach (['viewAny', 'view', 'export'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(array_map(static fn (string $a): string => "commission-configurations.{$a}", $abilities));

    return $actor;
}

/**
 * A reporter with one personal rule, another referent with one, a registry
 * rule whose recipient id collides with the reporter's id, and a plain
 * role-wide rule.
 *
 * @return array{referent: Referent, ruleA: CommissionConfiguration}
 */
function referentCommissionScopeFixture(): array
{
    $referent = Referent::factory()->create();
    $other = Referent::factory()->create();
    Registry::factory()->count(2)->create();

    $ruleA = CommissionConfiguration::factory()
        ->forRecipient('referent', $referent->id)
        ->create(['name' => 'Reporter A rule', 'recipient_role' => CommissionRecipientRole::Reporter]);
    CommissionConfiguration::factory()
        ->forRecipient('referent', $other->id)
        ->create(['name' => 'Reporter B rule', 'recipient_role' => CommissionRecipientRole::Reporter]);
    CommissionConfiguration::factory()
        ->forRecipient('registry', $referent->id)
        ->create(['name' => 'Colliding registry rule', 'recipient_role' => CommissionRecipientRole::Supplier]);
    CommissionConfiguration::factory()->create(['name' => 'Role-wide rule']);

    return ['referent' => $referent, 'ruleA' => $ruleA];
}

it('rows with referentId return only the rules whose recipient is that referent', function () {
    $fixture = referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor());

    $response = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'referentId' => $fixture['referent']->id,
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($fixture['ruleA']->id);
});

it('rows without referentId return every rule, unchanged', function () {
    referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonPath('pagination.total', 4);
});

it('the registry scope still works through the referent decorator', function () {
    $fixture = referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'registryId' => $fixture['referent']->id,
    ])->assertOk()->assertJsonPath('items.0.name', 'Colliding registry rule');
});

it('values are scoped to the referent', function () {
    $fixture = referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/values', [
        'columnId' => 'name',
        'referentId' => $fixture['referent']->id,
    ])->assertOk()->assertJsonPath('data.values', ['Reporter A rule']);
});

it('rejects a referentId that does not exist', function () {
    referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor());

    $this->postJson('/api/tables/commission-configurations/rows', ['startRow' => 0, 'endRow' => 25, 'referentId' => 999999])
        ->assertUnprocessable();
});

it('without viewAny the endpoint stays 403 even with referentId', function () {
    $fixture = referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor(['view']));

    $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'referentId' => $fixture['referent']->id,
    ])->assertForbidden();
});

it('export is scoped to the referent', function () {
    Storage::fake('local');
    $fixture = referentCommissionScopeFixture();
    Sanctum::actingAs(referentCommissionScopeActor(['viewAny', 'export']));

    $response = $this->postJson('/api/exports/commission-configurations', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
        'referentId' => $fixture['referent']->id,
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    expect($run->state['referentId'])->toBe($fixture['referent']->id)
        ->and($run->fresh()->row_count)->toBe(1);
});
