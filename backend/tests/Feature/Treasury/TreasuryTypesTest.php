<?php

use App\Enums\ChartAccountKind;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use App\Models\User;
use App\Rules\UniqueChartCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions
 */
function treasuryTypesUser(array $permissions): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity'] as $ability) {
        Permission::findOrCreate("treasury-types.{$ability}", 'web');
    }
    Permission::findOrCreate('chart-accounts.view', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function financialAccount(): ChartAccount
{
    $group = ChartAccount::factory()->state(['kind' => ChartAccountKind::Financial])->create();

    return ChartAccount::factory()->account($group)->create();
}

it('AC-004: creates, shows and updates a financial sub-account', function () {
    Sanctum::actingAs(treasuryTypesUser(['treasury-types.create', 'treasury-types.view', 'treasury-types.update']));
    $account = financialAccount();

    $id = $this->postJson('/api/treasury-types', ['chart_account_id' => $account->id, 'code' => 'T90.01.001', 'name' => 'Giroconto interno'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'T90.01.001')
        ->assertJsonPath('data.is_active', true)
        ->json('data.id');

    $this->getJson("/api/treasury-types/{$id}")->assertOk()->assertJsonPath('data.name', 'Giroconto interno');
    $this->patchJson("/api/treasury-types/{$id}", ['name' => 'Rinominato', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Rinominato')
        ->assertJsonPath('data.is_active', false);
});

it('AC-004: forbids every endpoint without the permission', function () {
    Sanctum::actingAs(treasuryTypesUser([]));
    $type = TreasuryType::factory()->create();

    $this->postJson('/api/treasury-types', ['chart_account_id' => $type->chart_account_id, 'code' => 'Z1', 'name' => 'x'])->assertForbidden();
    $this->getJson("/api/treasury-types/{$type->id}")->assertForbidden();
    $this->patchJson("/api/treasury-types/{$type->id}", ['name' => 'y'])->assertForbidden();
    $this->deleteJson("/api/treasury-types/{$type->id}")->assertForbidden();
    $this->getJson('/api/treasury-types/for-select')->assertForbidden();
});

it('AC-004: rejects a parent that is not an account of a financial group with 422', function () {
    Sanctum::actingAs(treasuryTypesUser(['treasury-types.create']));
    $revenueAccount = ChartAccount::factory()->account(ChartAccount::factory()->revenue()->create())->create();
    $financialGroup = ChartAccount::factory()->state(['kind' => ChartAccountKind::Financial])->create();

    foreach ([$revenueAccount->id, $financialGroup->id] as $parentId) {
        $this->postJson('/api/treasury-types', ['chart_account_id' => $parentId, 'code' => 'Z2', 'name' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('chart_account_id');
    }
});

it('AC-004: deletes an unused sub-account and answers 409 when a movement uses it', function () {
    Sanctum::actingAs(treasuryTypesUser(['treasury-types.delete']));
    $free = TreasuryType::factory()->create();
    $used = TreasuryType::factory()->create();
    TreasuryMovement::factory()->create(['treasury_type_id' => $used->id]);

    $this->deleteJson("/api/treasury-types/{$free->id}")->assertOk();
    $this->deleteJson("/api/treasury-types/{$used->id}")->assertStatus(409);

    $this->assertDatabaseMissing('treasury_types', ['id' => $free->id]);
    $this->assertDatabaseHas('treasury_types', ['id' => $used->id]);
});

it('AC-004: for-select lists only active financial sub-accounts', function () {
    Sanctum::actingAs(treasuryTypesUser(['treasury-types.view']));
    $active = TreasuryType::factory()->create(['code' => 'ZZ.1']);
    $inactive = TreasuryType::factory()->inactive()->create();

    $response = $this->getJson('/api/treasury-types/for-select')->assertOk();
    $ids = array_column($response->json('data.items'), 'id');

    expect($ids)->toContain($active->id)->not->toContain($inactive->id)
        ->and(collect($response->json('data.items'))->firstWhere('id', $active->id))
        ->toHaveKeys(['id', 'code', 'name', 'path', 'is_active', 'group', 'account']);
});

it('AC-003: the tree shows the seeded financial group 90 with its four accounts and sub-accounts of kind financial', function () {
    Sanctum::actingAs(treasuryTypesUser(['chart-accounts.view', 'treasury-types.update']));

    $data = $this->getJson('/api/chart-of-accounts/tree')->assertOk()->json('data');
    $group = collect($data['groups'])->firstWhere('code', '90');

    expect($group['kind'])->toBe('financial')
        ->and(array_column($group['accounts'], 'code'))->toBe(['90.01', '90.02', '90.03', '90.04'])
        ->and($data['abilities']['treasury_types'])->toBe(['create' => false, 'update' => true, 'delete' => false, 'view_activity' => false]);

    foreach ($group['accounts'] as $account) {
        expect($account['sub_accounts'])->toHaveCount(1)
            ->and($account['sub_accounts'][0])->toMatchArray([
                'source' => 'treasury_type', 'kind' => 'financial', 'nature' => null, 'color' => null,
                'is_institution_commission_default' => false,
            ])
            ->and($account['sub_accounts'][0])->toHaveKeys(['id', 'code', 'name', 'description', 'is_active']);
    }
});

it('a code already used anywhere in the chart of accounts is a 422, the own code is accepted', function () {
    Sanctum::actingAs(treasuryTypesUser(['treasury-types.create', 'treasury-types.update']));
    $account = financialAccount();
    $treasuryType = TreasuryType::factory()->create(['chart_account_id' => $account->id, 'code' => 'TT.1']);
    CostType::factory()->create(['code' => 'TT.COST']);
    RevenueType::factory()->create(['code' => 'TT.REV']);

    foreach (['TT.1', 'TT.COST', 'TT.REV', $account->code] as $taken) {
        $this->postJson('/api/treasury-types', ['chart_account_id' => $account->id, 'code' => $taken, 'name' => 'Dup'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    $this->patchJson("/api/treasury-types/{$treasuryType->id}", ['code' => 'TT.COST'])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->patchJson("/api/treasury-types/{$treasuryType->id}", ['code' => 'TT.1', 'name' => 'Kept'])->assertOk();
});

it('a financial sub-account code cannot be reused by a cost or revenue sub-account', function () {
    $account = financialAccount();
    TreasuryType::factory()->create(['chart_account_id' => $account->id, 'code' => 'TT.SHARED']);

    expect(Validator::make(['code' => 'TT.SHARED'], ['code' => UniqueChartCode::rules()])->fails())->toBeTrue();
});
