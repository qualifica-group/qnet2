<?php

use App\Enums\CommissionRecipientRole as Role;
use App\Enums\CommissionType;
use App\Enums\QuoteLineType;
use App\Enums\SupplierCommissionDirection;
use App\Models\ProductTypology;
use App\Models\QuoteLine;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Commessa contract data (spec 0201): GET /api/work-orders/{workOrder}/contract-data,
 * AC-001..AC-007.
 */
uses(RefreshDatabase::class);

function contractDataUrl(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/contract-data";
}

it('AC-001: effective revenue is the net without a direction and the Fornitore commission for RECEIVED, totals split', function () {
    $workOrder = WorkOrder::factory()->create();
    $consultancy = contractDataLine($workOrder, 'consultancy', 1000);
    $institution = contractDataLine($workOrder, 'institution', 2000, SupplierCommissionDirection::Received);
    contractDataCommission($institution, Role::Supplier, CommissionType::Percentage, 10, 200);
    $empty = ProductTypology::factory()->create(['name' => 'Zeta empty']);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $data = $this->getJson(contractDataUrl($workOrder))->assertOk()->assertJsonPath('success', true)->json('data');
    $lines = collect($data['lines'])->keyBy('quote_line_id');

    expect($lines[$consultancy->id]['effective_revenue'])->toBe('1000.00')
        ->and($lines[$consultancy->id]['supplier_commission_direction'])->toBeNull()
        ->and($lines[$institution->id]['effective_revenue'])->toBe('200.00')
        ->and($lines[$institution->id]['supplier_commission_direction'])->toBe('RECEIVED')
        ->and($data['totals']['net_amount'])->toBe('3000.00')
        ->and($data['totals']['effective_revenue'])->toBe('1200.00')
        ->and($data['totals']['commissions_amount'])->toBe('200.00')
        ->and($data['totals']['net_of_commissions'])->toBe('2800.00')
        ->and($data['totals'])->not->toHaveKeys(['consultancy_revenue', 'institution_revenue'])
        ->and(collect($data['totals']['typologies'])->keyBy('name')->map(fn (array $t): array => [$t['net_amount'], $t['effective_revenue']])->all())
        ->toBe([
            'Consultancy' => ['1000.00', '1000.00'],
            'Ente' => ['2000.00', '200.00'],
            'Zeta empty' => ['0.00', '0.00'],
        ])
        ->and(array_column($data['totals']['typologies'], 'name'))->toBe(['Consultancy', 'Ente', 'Zeta empty'])
        ->and($data['commissions_visible'])->toBeTrue();
});

it('GET returns the frozen line shape', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    contractDataCommission($line, Role::Supplier, CommissionType::Percentage, 15, 150);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $row = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines.0');

    expect($row)->toHaveKeys(['quote_line_id', 'product', 'typology', 'supplier_commission_direction', 'quantity', 'unit_price', 'net_amount', 'supplier_commission', 'commissions_amount', 'net_of_commissions', 'effective_revenue', 'warnings', 'payment'])
        ->and($row['product'])->toHaveKeys(['id', 'code', 'name'])
        ->and($row['typology'])->toHaveKeys(['id', 'code', 'name'])
        ->and($row['supplier_commission'])->toBe(['commission_type' => 'PERCENTAGE', 'value' => '15.0000', 'base_amount' => '1000.00', 'amount' => '150.00', 'is_stale' => false])
        ->and($row['quantity'])->toBe('1.00')
        ->and($row['unit_price'])->toBe('1000.00')
        ->and($row['payment'])->toBe(['status' => null, 'payment_agreement' => null, 'has_unpaid' => false]);
});

it('AC-002: commissions_amount sums every commission and net_of_commissions subtracts it', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    contractDataCommission($line, Role::Supplier, CommissionType::FixedAmount, 200, 200);
    contractDataCommission($line, Role::Commercial, CommissionType::FixedAmount, 50, 50);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $row = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines.0');

    expect($row['commissions_amount'])->toBe('250.00')->and($row['net_of_commissions'])->toBe('750.00');
});

it('AC-003: a RECEIVED line without Fornitore commission has 0.00 revenue and a warning', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    contractDataCommission($line, Role::Commercial, CommissionType::FixedAmount, 50, 50);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $row = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines.0');

    expect($row['effective_revenue'])->toBe('0.00')
        ->and($row['supplier_commission'])->toBeNull()
        ->and($row['warnings'])->toBe(['missing_supplier_commission']);
});

it('AC-004: FIXED_AMOUNT is the value itself, with no base', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    contractDataCommission($line, Role::Supplier, CommissionType::FixedAmount, 300, 300);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $row = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines.0');

    expect($row['supplier_commission']['amount'])->toBe('300.00')
        ->and($row['supplier_commission']['base_amount'])->toBeNull()
        ->and($row['supplier_commission']['is_stale'])->toBeFalse()
        ->and($row['warnings'])->toBe([]);
});

it('AC-005: a PERCENTAGE amount that differs from base x value is stale and the base is the margin', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    // A COST line imputed to the revenue line shrinks the margin base to 800.
    QuoteLine::factory()->create([
        'quote_id' => $workOrder->quote_id, 'line_type' => QuoteLineType::Cost,
        'offer_line_id' => $line->id, 'net_amount' => 200, 'unit_price' => 200, 'total_amount' => 200,
    ]);
    // 10% of 800 = 80, but the persisted amount is the old 100 (10% of 1000).
    contractDataCommission($line, Role::Supplier, CommissionType::Percentage, 10, 100);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $row = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines.0');

    expect($row['supplier_commission']['base_amount'])->toBe('800.00')
        ->and($row['supplier_commission']['amount'])->toBe('100.00')
        ->and($row['supplier_commission']['is_stale'])->toBeTrue()
        ->and($row['warnings'])->toBe(['stale_commission_base']);
});

it('AC-006: without commission visibility the commission fields and totals are null, RECEIVED revenue stays', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Received);
    contractDataCommission($line, Role::Supplier, CommissionType::Percentage, 10, 100);

    foreach (['view', 'viewContractData', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $role = RoleModel::create(['name' => 'no-commissions']);
    $role->givePermissionTo(['work-orders.view', 'work-orders.viewContractData', 'work-orders.viewAll']);
    $role->fieldPermissions()->create(['resource' => 'quotes', 'field' => 'commission_value', 'visible' => false, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $data = $this->getJson(contractDataUrl($workOrder))->assertOk()->json('data');
    $row = $data['lines'][0];

    expect($data['commissions_visible'])->toBeFalse()
        ->and($row['supplier_commission'])->toBeNull()
        ->and($row['commissions_amount'])->toBeNull()
        ->and($row['net_of_commissions'])->toBeNull()
        ->and($row['effective_revenue'])->toBe('100.00')
        ->and($data['totals']['commissions_amount'])->toBeNull()
        ->and($data['totals']['net_of_commissions'])->toBeNull()
        ->and($data['totals']['effective_revenue'])->toBe('100.00');
});

it('AC-007: GET is 403 without viewContractData and when the commessa is out of scope, 404 when missing', function () {
    $workOrder = WorkOrder::factory()->create();

    Sanctum::actingAs(workOrderPaymentsUserWith(['managePayments']));
    $this->getJson(contractDataUrl($workOrder))->assertForbidden();

    // Out of scope: permission held, but no viewAll and not on the team.
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData', 'view'], viewAll: false));
    $this->getJson(contractDataUrl($workOrder))->assertForbidden();

    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));
    $this->getJson('/api/work-orders/999999/contract-data')->assertNotFound();
});

it('AC-007: an in-scope team member can read, and the detail flags reflect the permissions', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderPaymentsUserWith(['viewContractData', 'view'], viewAll: false);
    $workOrder->supervisors()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson(contractDataUrl($workOrder))->assertOk();
    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()
        ->assertJsonPath('permissions.actions.view_contract_data', true)
        ->assertJsonPath('permissions.actions.manage_payments', false);
});

it('GET is unauthenticated 401', function () {
    $this->getJson(contractDataUrl(WorkOrder::factory()->create()))->assertUnauthorized();
});

it('runs the same number of queries whatever the number of lines (no N+1)', function () {
    $countQueries = function (int $lines): int {
        $workOrder = WorkOrder::factory()->create();
        foreach (range(1, $lines) as $index) {
            $line = contractDataLine($workOrder, $index % 2 ? 'institution' : 'consultancy', $index * 100);
            contractDataCommission($line, Role::Supplier, CommissionType::FixedAmount, 10, 10);
        }

        DB::enableQueryLog();
        test()->getJson(contractDataUrl($workOrder))->assertOk();
        $count = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::disableQueryLog();

        return $count;
    };
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    // The first request pays one-off permission-cache queries: warm it up.
    $countQueries(1);

    expect($countQueries(8))->toBe($countQueries(2));
});

it('spec 0202 AC-008: a PAID line keeps the net as revenue and raises no warning, whatever its typology code', function () {
    $workOrder = WorkOrder::factory()->create();
    $paid = contractDataLine($workOrder, 'institution', 1000, SupplierCommissionDirection::Paid);
    contractDataCommission($paid, Role::Supplier, CommissionType::FixedAmount, 90, 90);
    $disabled = contractDataLine($workOrder, 'institution', 500);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $lines = collect($this->getJson(contractDataUrl($workOrder))->assertOk()->json('data.lines'))->keyBy('quote_line_id');

    expect($lines[$paid->id]['effective_revenue'])->toBe('1000.00')
        ->and($lines[$paid->id]['supplier_commission_direction'])->toBe('PAID')
        ->and($lines[$paid->id]['warnings'])->toBe([])
        ->and($lines[$disabled->id]['effective_revenue'])->toBe('500.00')
        ->and($lines[$disabled->id]['supplier_commission_direction'])->toBeNull()
        ->and($lines[$disabled->id]['warnings'])->toBe([]);
});
