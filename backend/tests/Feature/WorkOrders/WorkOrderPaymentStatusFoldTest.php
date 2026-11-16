<?php

use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0403, AC-002: the commessa's own payment status folds into its lines,
 * then the column goes. RefreshDatabase is NOT used: dropping an FK-backed
 * column makes SQLite rebuild the table under a `PRAGMA foreign_keys` toggle,
 * a no-op inside its per-test transaction (the line payments would cascade
 * away). Each run starts and ends with `migrate:fresh`, only on in-memory
 * SQLite (same guard as EmploymentSiteBackfillTest).
 */
it('AC-002: keeps a line status, fills a line without one, creates a missing row and drops the column', function () {
    $connection = DB::connection();

    if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Refusing to run migrate:fresh: not an in-memory SQLite database.');
    }

    Artisan::call('migrate:fresh');
    $migration = include database_path('migrations/2026_12_13_100000_fold_work_order_payment_status_into_lines.php');
    $migration->down();
    $general = WorkOrderPaymentStatus::factory()->create();
    $own = WorkOrderPaymentStatus::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $withOwn = contractDataLine($workOrder, 'consultancy', 100);
    $withoutStatus = contractDataLine($workOrder, 'consultancy', 100);
    $withoutRow = contractDataLine($workOrder, 'consultancy', 100);
    WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $withOwn->id, 'work_order_payment_status_id' => $own->id]);
    WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $withoutStatus->id, 'payment_agreement' => 'kept']);
    $untouched = WorkOrder::factory()->create();
    $untouchedLine = contractDataLine($untouched, 'consultancy', 100);
    DB::table('work_orders')->where('id', $workOrder->id)->update(['work_order_payment_status_id' => $general->id]);

    $migration->up();

    $payments = WorkOrderLinePayment::query()->where('work_order_id', $workOrder->id)->get()->keyBy('quote_line_id');
    expect(Schema::hasColumn('work_orders', 'work_order_payment_status_id'))->toBeFalse()
        ->and($payments[$withOwn->id]->work_order_payment_status_id)->toBe($own->id)
        ->and($payments[$withoutStatus->id]->work_order_payment_status_id)->toBe($general->id)
        ->and($payments[$withoutStatus->id]->payment_agreement)->toBe('kept')
        ->and($payments[$withoutRow->id]->work_order_payment_status_id)->toBe($general->id)
        ->and($payments[$withoutRow->id]->has_unpaid)->toBeFalse()
        ->and($payments)->toHaveCount(3)
        ->and(WorkOrderLinePayment::query()->where('quote_line_id', $untouchedLine->id)->exists())->toBeFalse();

    Artisan::call('migrate:fresh');
});
