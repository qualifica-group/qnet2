<?php

use App\Authorization\CommissionSettlementsAuthorization;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('AC-016: permissions:sync creates the abilities of both settlement resources', function () {
    Artisan::call('permissions:sync');

    $names = Permission::query()->pluck('name');
    $pick = fn (string $resource): array => $names
        ->filter(fn (string $name): bool => str_starts_with($name, "{$resource}."))->sort()->values()->all();

    expect($pick('commission-settlements'))->toBe([
        'commission-settlements.adjust', 'commission-settlements.cancel', 'commission-settlements.create',
        'commission-settlements.delete', 'commission-settlements.export', 'commission-settlements.issue',
        'commission-settlements.update', 'commission-settlements.view', 'commission-settlements.viewActivity',
        'commission-settlements.viewAny',
    ])->and($pick('enasarco-parameters'))->toBe([
        'enasarco-parameters.create', 'enasarco-parameters.delete', 'enasarco-parameters.export',
        'enasarco-parameters.update', 'enasarco-parameters.view', 'enasarco-parameters.viewActivity',
        'enasarco-parameters.viewAny',
    ]);
});

it('AC-016: the Provvigioni agenti group lists the three settlement children after the accruals, only with view', function () {
    $flat = function (array $permissions): string {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        return json_encode(test()->getJson('/api/navigation')->assertOk()->json());
    };

    $none = $flat(['commission-settlements.viewAny', 'enasarco-parameters.create']);
    expect($none)->not->toContain('commission-settlement-runs')->not->toContain('enasarco-parameters');

    $settlements = $flat(['commission-settlements.view']);
    expect($settlements)->toContain('accounting-commissions')
        ->toContain('commission-settlement-runs')->toContain('commission-settlements')
        ->toContain('"icon":"play"')->toContain('"icon":"file-spreadsheet"')
        ->not->toContain('enasarco-parameters');

    $parameters = $flat(['enasarco-parameters.view']);
    expect($parameters)->toContain('enasarco-parameters')->toContain('"icon":"percent"')
        ->not->toContain('commission-settlement-runs');

    $all = $flat(['commission-accruals.view', 'commission-settlements.view', 'enasarco-parameters.view']);
    $order = array_map(fn (string $key) => strpos($all, "\"key\":\"{$key}\""), [
        'commission-accruals', 'commission-settlement-runs', 'commission-settlements', 'enasarco-parameters',
    ]);
    $sorted = $order;
    sort($sorted);
    expect($order)->toBe($sorted)->and(in_array(false, $order, true))->toBeFalse();
});

it('AC-016: the field catalogue exposes the settlement and ENASARCO fields to a role editor', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('roles.create', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Sanctum::actingAs($actor);

    $resources = collect($this->getJson('/api/authorization/fields')->assertOk()->json('data.resources'))->keyBy('resource');
    $keys = fn (string $resource): array => collect($resources[$resource]['fields'])->pluck('key')->all();

    expect($keys('commission-settlements'))->toBe([
        'notes', 'taxable_amount', 'vat_amount', 'withholding_amount',
        'enasarco_agent_amount', 'enasarco_principal_amount', 'net_payable',
    ])->and($keys('enasarco-parameters'))->toBe([
        'year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling', 'notes',
    ])->and($keys('agent-fiscal-profiles'))->toContain('vat_rate_id');
});

it('AC-017: notes are editable only on a draft for an actor who can update; amounts are read-only', function () {
    $authorization = app(CommissionSettlementsAuthorization::class);
    $updater = commissionSettlementUserWith(['update']);
    $viewer = commissionSettlementUserWith(['view']);
    $draft = CommissionSettlement::factory()->create();
    $issued = CommissionSettlement::factory()->issued()->create();

    expect($authorization->fieldPermissions($updater, $draft)['notes']->editable)->toBeTrue()
        ->and($authorization->fieldPermissions($updater, $issued)['notes']->editable)->toBeFalse()
        ->and($authorization->fieldPermissions($viewer, $draft)['notes']->editable)->toBeFalse()
        ->and($authorization->fieldPermissions($updater, $draft)['net_payable']->editable)->toBeFalse()
        ->and($authorization->fieldPermissions($updater, $draft)['net_payable']->visible)->toBeTrue();
});

it('AC-016: the action permissions follow the matching ability', function () {
    $authorization = app(CommissionSettlementsAuthorization::class);
    $draft = CommissionSettlement::factory()->create();

    $actions = $authorization->actionPermissions(commissionSettlementUserWith(['update', 'issue', 'adjust']), $draft);

    expect($actions)->toBe([
        'recalculate' => true, 'issue' => true, 'cancel' => false, 'delete' => false,
        'adjust' => true, 'export' => false, 'view_activity' => false,
    ]);
});
