<?php

use App\Enums\InstallmentStatus;
use App\Enums\PurchaseInstallmentStatus;
use App\Enums\ResidualMode;
use App\Enums\WriteOffReason;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseMigrations, NOT RefreshDatabase: one test replays a real
 * `migrate:rollback`/`migrate` of the write-off migration.
 */
uses(DatabaseMigrations::class);

const WRITE_OFF_MIGRATION = ['database/migrations/2026_11_20_100000_add_write_off_to_installments_tables.php'];

const WRITE_OFF_COLUMNS = ['write_off_amount', 'write_off_reason', 'write_off_note'];

function residualOf(InvoiceInstallment|PurchaseInvoiceInstallment $model): float
{
    return round((float) $model->fresh()->residual_amount, 2);
}

it('adds the write-off columns and keeps the residual index on both installment tables', function () {
    foreach (['invoice_installments', 'purchase_invoice_installments'] as $table) {
        expect(Schema::hasColumns($table, [...WRITE_OFF_COLUMNS, 'residual_amount']))->toBeTrue()
            ->and(collect(Schema::getIndexes($table))->pluck('name'))->toContain($table.'_residual_due_index');
    }
});

it('subtracts the write-off from the generated residual on the receivable side', function () {
    $open = InvoiceInstallment::factory()->create(['amount' => '1000.00']);
    $partial = InvoiceInstallment::factory()->create(['amount' => '1000.00', 'collected_amount' => '999.80', 'write_off_amount' => '0.20', 'write_off_reason' => 'rounding']);
    $zero = InvoiceInstallment::factory()->writtenOff('1000.00', 'bad_debt')->create(['amount' => '1000.00']);

    expect(residualOf($open))->toBe(1000.0)
        ->and(residualOf($partial))->toBe(0.0)
        ->and(residualOf($zero))->toBe(0.0)
        ->and($zero->fresh()->write_off_reason)->toBe(WriteOffReason::BadDebt)
        ->and($zero->fresh()->write_off_amount)->toBe('1000.00')
        ->and($partial->fresh()->residualAmount())->toBe('0.00');
});

it('subtracts the write-off from the generated residual on the payable side', function () {
    $open = PurchaseInvoiceInstallment::factory()->create(['amount' => '500.00']);
    $partial = PurchaseInvoiceInstallment::factory()->create(['amount' => '500.00', 'paid_amount' => '499.50', 'write_off_amount' => '0.50', 'write_off_reason' => 'rounding']);
    $zero = PurchaseInvoiceInstallment::factory()->writtenOff('500.00', 'bad_debt')->create(['amount' => '500.00']);

    expect(residualOf($open))->toBe(500.0)
        ->and(residualOf($partial))->toBe(0.0)
        ->and(residualOf($zero))->toBe(0.0)
        ->and($partial->fresh()->residualAmount())->toBe('0.00');
});

it('treats an installment with only a write-off as paid', function () {
    $active = InvoiceInstallment::factory()->writtenOff()->create(['amount' => '10.00']);
    $passive = PurchaseInvoiceInstallment::factory()->writtenOff()->create(['amount' => '10.00']);

    expect($active->status())->toBe(InstallmentStatus::Paid)
        ->and($passive->status())->toBe(PurchaseInstallmentStatus::Paid)
        ->and($passive->isPaid())->toBeTrue()
        ->and(InvoiceInstallment::factory()->create()->status())->toBe(InstallmentStatus::Unpaid)
        ->and(PurchaseInvoiceInstallment::factory()->create()->isPaid())->toBeFalse();
});

it('exposes the write-off enums and the suggestion threshold', function () {
    expect(WriteOffReason::values())->toBe(['rounding', 'bad_debt'])
        ->and(ResidualMode::WriteOff->value)->toBe('write_off')
        ->and(config('treasury.write_off_suggest_threshold'))->toBe('5.00');
});

it('rolls the migration down to the previous residual expression and up again', function () {
    expect(Artisan::call('migrate:rollback', ['--path' => WRITE_OFF_MIGRATION]))->toBe(0);

    foreach (['invoice_installments', 'purchase_invoice_installments'] as $table) {
        expect(Schema::hasColumn($table, 'write_off_amount'))->toBeFalse()
            ->and(Schema::hasColumn($table, 'residual_amount'))->toBeTrue()
            ->and(collect(Schema::getIndexes($table))->pluck('name'))->toContain($table.'_residual_due_index');
    }

    expect(Artisan::call('migrate', ['--path' => WRITE_OFF_MIGRATION]))->toBe(0);

    $row = InvoiceInstallment::factory()->writtenOff()->create(['amount' => '100.00', 'collected_amount' => '99.80']);

    expect(Schema::hasColumn('invoice_installments', 'write_off_amount'))->toBeTrue()
        ->and(residualOf($row))->toBe(0.0)
        ->and(DB::table('purchase_invoice_installments')->count())->toBe(0);
});
