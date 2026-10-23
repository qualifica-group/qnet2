<?php

use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direction of the Supplier commission on the rule (spec 0306, D-1/D-9).
 */
uses(RefreshDatabase::class);

function supplierDirectionRuleActor(): User
{
    $actor = User::factory()->create();

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
        $actor->givePermissionTo("commission-configurations.{$ability}");
    }

    return $actor;
}

/** @return array<string, mixed> */
function supplierDirectionRulePayload(string $role, array $overrides = []): array
{
    return [
        'name' => 'Rule '.$role,
        'recipient_role' => $role,
        'application_scope' => 'PRODUCT',
        'product_id' => Product::factory()->create()->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
        ...$overrides,
    ];
}

it('AC-001: a SUPPLIER rule needs a direction, a COMMERCIAL rule discards it', function () {
    Sanctum::actingAs(supplierDirectionRuleActor());

    $this->postJson('/api/commission-configurations', supplierDirectionRulePayload('SUPPLIER'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_commission_direction')
        ->assertJsonPath('errors.supplier_commission_direction.0', __('commission_configurations.direction_required'));

    $this->postJson('/api/commission-configurations', supplierDirectionRulePayload('SUPPLIER', ['supplier_commission_direction' => 'BOTH']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_commission_direction');

    $this->postJson('/api/commission-configurations', supplierDirectionRulePayload('SUPPLIER', ['supplier_commission_direction' => 'PAID']))
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_direction', 'PAID');

    $this->postJson('/api/commission-configurations', supplierDirectionRulePayload('COMMERCIAL', ['supplier_commission_direction' => 'RECEIVED']))
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_direction', null);
});

it('AC-002: a PATCH changing the role clears or requires the direction', function () {
    Sanctum::actingAs(supplierDirectionRuleActor());
    $supplier = CommissionConfiguration::factory()->create(['recipient_role' => 'SUPPLIER', 'supplier_commission_direction' => 'RECEIVED']);
    $commercial = CommissionConfiguration::factory()->create(['recipient_role' => 'COMMERCIAL']);

    $this->patchJson("/api/commission-configurations/{$supplier->id}", ['recipient_role' => 'COMMERCIAL'])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', null);

    $this->patchJson("/api/commission-configurations/{$commercial->id}", ['recipient_role' => 'SUPPLIER'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_commission_direction');

    $this->patchJson("/api/commission-configurations/{$commercial->id}", ['recipient_role' => 'SUPPLIER', 'supplier_commission_direction' => 'RECEIVED'])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', 'RECEIVED');

    // Persisted SUPPLIER rule: an unrelated edit keeps the direction, clearing it is refused.
    $this->patchJson("/api/commission-configurations/{$commercial->id}", ['priority' => 3])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', 'RECEIVED');
    $this->patchJson("/api/commission-configurations/{$commercial->id}", ['supplier_commission_direction' => null])
        ->assertUnprocessable();
});

it('AC-002: an actor without the field permission does not see the direction', function () {
    $role = Role::create(['name' => 'direction-hidden']);
    Permission::findOrCreate('commission-configurations.view');
    $role->givePermissionTo('commission-configurations.view');
    $role->fieldPermissions()->create(['resource' => 'commission-configurations', 'field' => 'supplier_commission_direction', 'visible' => false, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);
    $rule = CommissionConfiguration::factory()->create(['recipient_role' => 'SUPPLIER', 'supplier_commission_direction' => 'PAID']);

    $this->getJson("/api/commission-configurations/{$rule->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.supplier_commission_direction');
});

it('AC-003: the table exposes the direction column with badges and a working set filter', function () {
    Sanctum::actingAs(supplierDirectionRuleActor());
    $received = CommissionConfiguration::factory()->create(['recipient_role' => 'SUPPLIER', 'supplier_commission_direction' => 'RECEIVED']);
    CommissionConfiguration::factory()->create(['recipient_role' => 'SUPPLIER', 'supplier_commission_direction' => 'PAID']);
    CommissionConfiguration::factory()->create(['recipient_role' => 'COMMERCIAL']);

    $column = collect($this->getJson('/api/tables/commission-configurations/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'supplier_commission_direction');
    expect($column)->not->toBeNull()
        ->and($column['type'])->toBe('badge')
        ->and(collect($column['badges'])->pluck('value')->all())->toBe(['RECEIVED', 'PAID']);

    $rows = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['supplier_commission_direction' => ['filterType' => 'set', 'values' => ['RECEIVED']]],
    ])->assertOk();

    expect(collect($rows->json('items'))->pluck('id')->all())->toBe([$received->id])
        ->and($rows->json('items.0.supplier_commission_direction'))->toBe('RECEIVED');
});
