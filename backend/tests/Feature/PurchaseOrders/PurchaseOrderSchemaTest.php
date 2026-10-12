<?php

use App\Enums\PurchaseOrderMergeAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderMergeLog;
use App\Models\PurchaseOrderNumberSequence;
use App\Models\PurchaseRequestLine;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const PURCHASE_ORDER_TABLES = [
    'cost_types', 'purchase_order_number_sequences', 'purchase_orders', 'purchase_order_lines',
    'purchase_order_sector', 'purchase_order_merge_logs',
];

it('creates the six tables of the contract', function () {
    foreach (PURCHASE_ORDER_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    expect(Schema::hasColumns('purchase_orders', [
        'number', 'year', 'supplier_id', 'company_id', 'company_site_id', 'operational_site_id', 'order_date',
        'description', 'general_notes', 'payment_method_id', 'is_internal_user', 'customer_id', 'work_order_id',
        'quote_id', 'opportunity_id', 'sector_notes', 'status', 'taxable_total', 'vat_total', 'grand_total',
        'copy_group_id', 'created_by',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('purchase_order_lines', [
            'purchase_order_id', 'position', 'product_id', 'description', 'cost_type_id', 'unit_of_measure_id',
            'quantity', 'unit_price', 'vat_rate_id', 'taxable_amount', 'vat_amount', 'total_amount',
            'work_order_id', 'purchase_request_line_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('purchase_order_merge_logs', [
            'destination_purchase_order_id', 'source_purchase_order_id', 'source_code', 'source_snapshot',
            'moved_line_ids', 'source_action', 'performed_by', 'created_at',
        ]))->toBeTrue();
});

it('defaults a new order to draft with zero totals and resolves its relations', function () {
    $order = PurchaseOrder::factory()->create();
    $line = PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id]);
    $order->sectors()->attach(Sector::factory()->create());
    PurchaseOrderMergeLog::factory()->create(['destination_purchase_order_id' => $order->id]);
    $order = $order->fresh();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->taxable_total)->toBe('0.00')
        ->and($order->code())->toBe($order->number.'/'.$order->year)
        ->and($order->supplier->is_supplier)->toBeTrue()
        ->and($order->companySite->company_id)->toBe($order->company_id)
        ->and($order->createdBy)->not->toBeNull()
        ->and($order->lines->pluck('id')->all())->toBe([$line->id])
        ->and($order->sectors)->toHaveCount(1)
        ->and($order->mergeLogs)->toHaveCount(1)
        ->and($line->costType)->toBeInstanceOf(CostType::class)
        ->and($line->purchaseOrder->is($order))->toBeTrue();
});

it('keeps the lines ordered by position', function () {
    $order = PurchaseOrder::factory()->create();
    $second = PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'position' => 2]);
    $first = PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'position' => 1]);

    expect($order->lines->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('does not mass assign number, year, status, totals, copy group or creator', function () {
    $order = new PurchaseOrder;
    $order->fill(['number' => 9, 'year' => 2020, 'status' => 'approved', 'grand_total' => '9.00', 'created_by' => 1, 'copy_group_id' => 'x', 'description' => 'ok']);

    expect($order->getAttributes())->toBe(['description' => 'ok']);

    $line = new PurchaseOrderLine;
    $line->fill(['total_amount' => '9.00', 'purchase_request_line_id' => 5, 'description' => 'ok']);

    expect($line->getAttributes())->toBe(['description' => 'ok']);
});

it('enforces unique (company, year, number) on orders and (company, year) on sequences', function () {
    $order = PurchaseOrder::factory()->create();

    expect(fn () => PurchaseOrder::factory()->create([
        'company_id' => $order->company_id, 'company_site_id' => $order->company_site_id,
        'year' => $order->year, 'number' => $order->number,
    ]))->toThrow(QueryException::class);

    $sequence = PurchaseOrderNumberSequence::factory()->create();

    expect(fn () => PurchaseOrderNumberSequence::factory()->create([
        'company_id' => $sequence->company_id, 'year' => $sequence->year,
    ]))->toThrow(QueryException::class);
});

it('links a request line to at most one order line and nulls the link when the request line goes', function () {
    $requestLine = PurchaseRequestLine::factory()->create();
    $orderLine = PurchaseOrderLine::factory()->create();
    $orderLine->forceFill(['purchase_request_line_id' => $requestLine->id])->save();

    expect($requestLine->purchaseOrderLine->is($orderLine))->toBeTrue()
        ->and($orderLine->purchaseRequestLine->is($requestLine))->toBeTrue();

    $other = PurchaseOrderLine::factory()->create();

    expect(fn () => $other->forceFill(['purchase_request_line_id' => $requestLine->id])->save())
        ->toThrow(QueryException::class);

    $requestLine->delete();

    expect($orderLine->fresh()->purchase_request_line_id)->toBeNull();
});

it('restricts deleting a cost type or a supplier in use, cascades lines and pivots with the order', function () {
    $line = PurchaseOrderLine::factory()->create();
    $order = $line->purchaseOrder;
    $order->sectors()->attach(Sector::factory()->create());

    expect(fn () => $line->costType->delete())->toThrow(QueryException::class)
        ->and(fn () => $order->supplier->delete())->toThrow(QueryException::class);

    $order->delete();

    expect(PurchaseOrderLine::query()->count())->toBe(0)
        ->and(DB::table('purchase_order_sector')->count())->toBe(0);
});

it('keeps a merge log with a null source when the source order is deleted', function () {
    $source = PurchaseOrder::factory()->create();
    $log = PurchaseOrderMergeLog::factory()->create([
        'source_purchase_order_id' => $source->id,
        'source_action' => PurchaseOrderMergeAction::Partial,
        'source_snapshot' => ['lines' => [1, 2]],
        'moved_line_ids' => [1],
    ]);

    $source->delete();
    $log = $log->fresh();

    expect($log->source_purchase_order_id)->toBeNull()
        ->and($log->source_action)->toBe(PurchaseOrderMergeAction::Partial)
        ->and($log->source_snapshot)->toBe(['lines' => [1, 2]])
        ->and($log->moved_line_ids)->toBe([1]);
});

it('allows the transitions of the D-9 matrix and nothing else', function () {
    $matrix = [
        'draft' => ['in_validation', 'approved', 'cancelled'],
        'in_validation' => ['draft', 'approved', 'cancelled'],
        'approved' => ['in_validation', 'sent_to_supplier', 'cancelled'],
        'sent_to_supplier' => ['partially_fulfilled', 'fulfilled', 'cancelled'],
        'partially_fulfilled' => ['sent_to_supplier', 'fulfilled'],
        'fulfilled' => ['partially_fulfilled'],
        'cancelled' => ['draft'],
    ];

    expect(PurchaseOrderStatus::values())->toBe(array_keys($matrix));

    foreach (PurchaseOrderStatus::cases() as $status) {
        expect(array_map(fn ($to) => $to->value, $status->allowedTransitions()))->toBe($matrix[$status->value], $status->value);
    }

    expect(PurchaseOrderStatus::Draft->canTransitionTo(PurchaseOrderStatus::SentToSupplier))->toBeFalse()
        ->and(PurchaseOrderStatus::Approved->canTransitionTo(PurchaseOrderStatus::SentToSupplier))->toBeTrue();
});

it('is editable only in draft and in validation', function () {
    $editable = array_filter(PurchaseOrderStatus::cases(), fn ($status) => $status->isEditable());

    expect(array_map(fn ($status) => $status->value, array_values($editable)))->toBe(['draft', 'in_validation'])
        ->and(PurchaseOrderStatus::Draft->label())->toBe('Draft');
});

it('registers the purchase_order alias for attachments and the morph map', function () {
    expect(config('attachments.attachable_types.purchase_order'))->toBe(PurchaseOrder::class)
        ->and(Relation::getMorphedModel('purchase_order'))->toBe(PurchaseOrder::class);
});
