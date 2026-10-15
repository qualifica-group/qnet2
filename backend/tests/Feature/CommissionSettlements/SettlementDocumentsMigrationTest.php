<?php

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\PurchaseInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const SETTLEMENT_DOCUMENT_MIGRATIONS = [
    '2026_10_19_100000_add_withholding_and_enasarco_to_purchase_invoices_table.php' => [
        'purchase_invoices' => ['withholding_amount', 'enasarco_amount'],
    ],
    '2026_10_19_100100_add_supplier_registry_id_to_referents_table.php' => [
        'referents' => ['supplier_registry_id'],
    ],
    '2026_10_19_100200_add_payment_defaults_to_agent_fiscal_profiles_table.php' => [
        'agent_fiscal_profiles' => ['payment_method_id', 'cost_type_id'],
    ],
    '2026_10_19_100300_add_document_and_approval_to_commission_settlements_table.php' => [
        'commission_settlements' => [
            'document_number', 'document_date', 'document_taxable_amount', 'document_vat_amount',
            'document_withholding_amount', 'document_enasarco_amount', 'document_total_amount',
            'document_received_at', 'document_received_by', 'approved_at', 'approved_by', 'purchase_invoice_id',
        ],
    ],
];

it('AC-017: every migration rolls back and re-applies', function () {
    foreach (array_reverse(SETTLEMENT_DOCUMENT_MIGRATIONS, true) as $file => $tables) {
        $migration = require database_path("migrations/{$file}");
        $migration->down();

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                expect(Schema::hasColumn($table, $column))->toBeFalse("{$table}.{$column} after down");
            }
        }
    }

    foreach (SETTLEMENT_DOCUMENT_MIGRATIONS as $file => $tables) {
        $migration = require database_path("migrations/{$file}");
        $migration->up();

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                expect(Schema::hasColumn($table, $column))->toBeTrue("{$table}.{$column} after up");
            }
        }
    }
});

it('AC-017: existing purchase invoices get 0.00 withholding and ENASARCO', function () {
    $file = array_key_first(SETTLEMENT_DOCUMENT_MIGRATIONS);
    $migration = require database_path("migrations/{$file}");
    $existing = PurchaseInvoice::factory()->create();

    $migration->down();
    $migration->up();

    $row = DB::table('purchase_invoices')->where('id', $existing->id)->first();
    $invoice = PurchaseInvoice::query()->findOrFail($existing->id);

    expect((float) $row->withholding_amount)->toBe(0.0)
        ->and((float) $row->enasarco_amount)->toBe(0.0)
        ->and($invoice->withholding_amount)->toBe('0.00')
        ->and($invoice->enasarco_amount)->toBe('0.00');
});

it('computes the payable amount as total minus withholding and ENASARCO', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'total_amount' => '1220.00', 'withholding_amount' => '115.00', 'enasarco_amount' => '85.00',
    ]);

    expect($invoice->fresh()->payableAmount())->toBe('1020.00');
});

it('exposes the document states and the valid statuses of a settlement', function () {
    $received = CommissionSettlement::factory()->documentReceived()->create();
    $approved = CommissionSettlement::factory()->approved()->create();

    expect($received->fresh()->status)->toBe(CommissionSettlementStatus::DocumentReceived)
        ->and($received->fresh()->document_number)->toBe('FA-1')
        ->and($approved->fresh()->status)->toBe(CommissionSettlementStatus::Approved)
        ->and($approved->fresh()->approver)->not->toBeNull()
        ->and(CommissionSettlementStatus::validStatuses())->toBe([
            CommissionSettlementStatus::Issued,
            CommissionSettlementStatus::DocumentReceived,
            CommissionSettlementStatus::Approved,
        ]);
});

it('links one purchase invoice to at most one settlement', function () {
    $invoice = PurchaseInvoice::factory()->create();
    $settlement = CommissionSettlement::factory()->approved()->create(['purchase_invoice_id' => $invoice->id]);

    expect($invoice->commissionSettlement->is($settlement))->toBeTrue()
        ->and(fn () => CommissionSettlement::factory()->approved()->create(['purchase_invoice_id' => $invoice->id]))
        ->toThrow(QueryException::class);
});
