<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseMigrations, NOT RefreshDatabase: the test replays a real
 * `migrate:rollback`/`migrate` (same reason as PurchaseOrderMigrationTest).
 */
uses(DatabaseMigrations::class);

it('AC-001: rolls back the three payable tables and re-applies them with the virtual residual working', function () {
    $tables = ['purchase_invoices', 'purchase_invoice_purchase_order', 'purchase_invoice_installments'];
    // `--path`, not `--step`: a step count breaks whenever a newer migration lands.
    $migrations = [
        'database/migrations/2026_10_14_100000_create_purchase_invoices_table.php',
        'database/migrations/2026_10_14_100100_create_purchase_invoice_purchase_order_table.php',
        'database/migrations/2026_10_14_100200_create_purchase_invoice_installments_table.php',
    ];

    expect(Artisan::call('migrate:rollback', ['--path' => $migrations]))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }

    expect(Artisan::call('migrate', ['--path' => $migrations]))->toBe(0);

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    $installment = purchaseInstallmentOf(purchaseDocument(), ['amount' => '100.00', 'paid_amount' => '30.00']);

    expect(Schema::hasColumns('purchase_invoice_installments', ['paid_amount', 'paid_at', 'financial_account_id', 'redistribution_snapshot', 'residual_amount']))->toBeTrue()
        ->and(DB::table('purchase_invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual('70');
});
