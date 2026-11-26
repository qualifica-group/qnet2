<?php

use App\Enums\PurchaseInvoiceType;
use App\Models\CostType;
use App\Models\Payslip;
use App\Models\PurchaseInvoiceLine;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Spec 0431, AC-009 — schema, cost type data migration, payslip type.
uses(RefreshDatabase::class);

/** The data migration of the payslip cost type, as a callable instance. */
function payslipCostTypeMigration(): object
{
    return require database_path('migrations/2026_12_27_100100_add_payroll_salaries_cost_type.php');
}

it('AC-009: the migration creates the payslip cost type once, under the chart account 63.01', function () {
    $costType = CostType::query()->where('code', config('payroll.payslip.cost_type_code'))->firstOrFail();

    expect($costType->name)->toBe('Retribuzioni al personale')
        ->and(DB::table('chart_accounts')->where('id', $costType->chart_account_id)->value('code'))->toBe('63.01');

    payslipCostTypeMigration()->up();

    expect(CostType::query()->where('code', '63.01.001')->count())->toBe(1);
});

it('AC-009: the rollback removes the cost type only while no line uses it', function () {
    $costType = CostType::query()->where('code', '63.01.001')->firstOrFail();
    PurchaseInvoiceLine::factory()->create(['cost_type_id' => $costType->id]);

    payslipCostTypeMigration()->down();
    expect(CostType::query()->whereKey($costType->id)->exists())->toBeTrue();

    PurchaseInvoiceLine::query()->delete();
    payslipCostTypeMigration()->down();
    expect(CostType::query()->whereKey($costType->id)->exists())->toBeFalse();
});

it('AC-009: a payslip belongs to a payslip document and the person keeps the supplier registry', function () {
    $payslip = Payslip::factory()->create();
    $registry = Registry::factory()->create(['is_supplier' => true]);
    $user = User::factory()->create();
    $user->forceFill(['supplier_registry_id' => $registry->id])->save();

    expect($payslip->purchaseInvoice->document_type)->toBe(PurchaseInvoiceType::Payslip)
        ->and($payslip->purchaseInvoice->payslip->is($payslip))->toBeTrue()
        ->and($user->fresh()->supplierRegistry->is($registry))->toBeTrue()
        ->and(PurchaseInvoiceType::values())->toContain('payslip');
});
