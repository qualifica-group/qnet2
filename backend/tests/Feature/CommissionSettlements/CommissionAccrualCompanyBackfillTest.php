<?php

use App\Models\Invoice;
use App\Models\Referent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DatabaseMigrations, NOT RefreshDatabase: the test replays a real
 * `migrate:rollback`/`migrate` (same reason as PurchaseOrderMigrationTest).
 */
uses(DatabaseMigrations::class);

const ACCRUAL_ALTER_MIGRATION = ['database/migrations/2026_10_16_100400_alter_commission_accruals_for_settlements.php'];

/**
 * Raw insert: the migration is rolled back, so the model/factory columns
 * (company_id, notes, ...) do not exist at that point.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertLegacyAccrual(array $overrides = []): int
{
    return DB::table('commission_accruals')->insertGetId([
        'recipient_role' => 'COMMERCIAL',
        'recipient_type' => 'referent',
        'recipient_id' => Referent::factory()->create()->id,
        'type' => 'ACCRUAL',
        'base_amount' => '100.00',
        'amount' => '50.00',
        'accrued_on' => '2026-09-10',
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

it('AC-013: the migration backfills company_id from the invoice and the rollback restores the old schema', function () {
    $invoice = Invoice::factory()->create();

    expect(Artisan::call('migrate:rollback', ['--path' => ACCRUAL_ALTER_MIGRATION]))->toBe(0);

    expect(Schema::hasColumn('commission_accruals', 'company_id'))->toBeFalse()
        ->and(Schema::hasColumn('commission_accruals', 'commission_settlement_id'))->toBeFalse()
        ->and(Schema::hasColumn('commission_accruals', 'notes'))->toBeFalse()
        ->and(Schema::hasColumn('commission_accruals', 'created_by'))->toBeFalse();

    $withInvoice = insertLegacyAccrual(['invoice_id' => $invoice->id]);
    $orphan = insertLegacyAccrual();

    expect(Artisan::call('migrate', ['--path' => ACCRUAL_ALTER_MIGRATION]))->toBe(0);

    expect(DB::table('commission_accruals')->where('id', $withInvoice)->value('company_id'))->toBe($invoice->company_id)
        ->and(DB::table('commission_accruals')->where('id', $orphan)->value('company_id'))->toBeNull();
});

it('AC-013: the rollback deletes ADJUSTMENT rows and keeps the other movements', function () {
    $invoice = Invoice::factory()->create();
    $kept = DB::table('commission_accruals')->insertGetId([
        'invoice_id' => $invoice->id,
        'company_id' => $invoice->company_id,
        'recipient_role' => 'COMMERCIAL',
        'recipient_type' => 'referent',
        'recipient_id' => Referent::factory()->create()->id,
        'type' => 'ACCRUAL',
        'base_amount' => '100.00',
        'amount' => '50.00',
        'accrued_on' => '2026-09-10',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $adjustment = DB::table('commission_accruals')->insertGetId([
        'company_id' => $invoice->company_id,
        'recipient_role' => null,
        'recipient_type' => 'referent',
        'recipient_id' => Referent::factory()->create()->id,
        'type' => 'ADJUSTMENT',
        'base_amount' => '0.00',
        'amount' => '-20.00',
        'accrued_on' => '2026-09-11',
        'notes' => 'Correction',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Artisan::call('migrate:rollback', ['--path' => ACCRUAL_ALTER_MIGRATION]))->toBe(0);

    expect(DB::table('commission_accruals')->where('id', $adjustment)->exists())->toBeFalse()
        ->and(DB::table('commission_accruals')->where('id', $kept)->exists())->toBeTrue();
});
