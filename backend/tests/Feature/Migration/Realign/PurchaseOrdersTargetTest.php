<?php

use App\Enums\PurchaseOrderStatus;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\PurchaseOrdersTarget;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedMigrationsConfig();
    $this->anchors = legacyPurchaseOrderAnchors();
    runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord([
        'lines' => [legacyPurchaseOrderLine(), legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'description' => 'To disappear'])],
    ])]);
    Http::swap(new Factory);
});

/**
 * @param  array<string, mixed>  $legacy
 */
function realignPurchaseOrder(array $legacy, bool $apply = true): LegacyRealignReport
{
    fakeLegacyRealign('purchase-orders', [$legacy['id']], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(PurchaseOrdersTarget::class), '2026-10-23', $apply, $report);
    Http::swap(new Factory);

    return $report;
}

it('AC-016 aligns header, status and lines and leaves number, company and creator', function () {
    $before = PurchaseOrder::query()->where('old_id', 800)->firstOrFail();

    $report = realignPurchaseOrder(legacyPurchaseOrderRecord([
        'number' => 999,
        'description' => 'Changed',
        'status' => 'approved',
        'lines' => [
            legacyPurchaseOrderLine(['quantity' => 5]),
            legacyPurchaseOrderLine(['id' => 1502, 'position' => 3, 'description' => 'New line', 'quantity' => 1, 'unit_price' => 4]),
        ],
    ]));

    $order = $before->fresh();

    expect($report->counters['purchase-orders'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($order->description)->toBe('Changed')
        ->and($order->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($order->number)->toBe($before->number)
        ->and($order->company_id)->toBe($before->company_id)
        ->and($order->created_by)->toBe($before->created_by)
        ->and((float) PurchaseOrderLine::query()->where('old_id', 1500)->value('quantity'))->toBe(5.0)
        ->and(PurchaseOrderLine::query()->where('old_id', 1502)->exists())->toBeTrue()
        ->and(PurchaseOrderLine::query()->where('old_id', 1501)->exists())->toBeTrue()
        ->and((float) $order->taxable_total)->toBe(52.5 + 21.0 + 4.0)
        ->and($report->messages)->toContain(['purchase-orders', '800', 'warning', 'Line 1501 is no longer in the legacy; kept.']);
});

it('generates the receipt of an order that becomes fulfilled, once', function () {
    $legacy = legacyPurchaseOrderRecord(['status' => 'fulfilled', 'updated_at' => '2026-10-25 08:00:00', 'lines' => [
        legacyPurchaseOrderLine(),
        legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'description' => 'To disappear']),
    ]]);

    realignPurchaseOrder($legacy);
    $second = realignPurchaseOrder($legacy);

    expect(PurchaseOrderReceipt::query()->count())->toBe(1)
        ->and((float) PurchaseOrderLine::query()->where('old_id', 1500)->value('received_quantity'))->toBe(2.0)
        ->and($second->counters['purchase-orders'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('AC-025 keeps an order fulfilled on qnet-2 when the legacy status stays behind', function () {
    $lines = [legacyPurchaseOrderLine(), legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'description' => 'To disappear'])];
    realignPurchaseOrder(legacyPurchaseOrderRecord(['status' => 'fulfilled', 'lines' => $lines]));

    $report = realignPurchaseOrder(legacyPurchaseOrderRecord(['status' => 'draft', 'description' => 'Changed', 'lines' => $lines]));

    $order = PurchaseOrder::query()->where('old_id', 800)->firstOrFail();

    expect($order->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($order->description)->toBe('Changed')
        ->and(collect($report->messages)->pluck(3)->implode(' '))->toContain('has receipts or invoices');
});

it('adds no receipt to an order already received and keeps a quantity below it', function () {
    $line = PurchaseOrderLine::query()->where('old_id', 1500)->firstOrFail();
    $line->forceFill(['received_quantity' => 2])->saveQuietly();

    $report = realignPurchaseOrder(legacyPurchaseOrderRecord(['status' => 'fulfilled', 'lines' => [
        legacyPurchaseOrderLine(['quantity' => 1]),
        legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'description' => 'To disappear']),
    ]]));

    $warnings = array_column($report->messages, 3);

    expect(PurchaseOrderReceipt::query()->count())->toBe(0)
        ->and((float) $line->fresh()->quantity)->toBe(2.0)
        ->and($warnings)->toContain('Fulfilled in the legacy, but the order already has receipts on qnet-2: none is generated.');
});

it('keeps the number reference of a reassigned order in the notes', function () {
    PurchaseOrder::query()->where('old_id', 800)->update(['general_notes' => "Old\n\nNumero legacy 12 (protocollo 05/03/2025-120)"]);

    realignPurchaseOrder(legacyPurchaseOrderRecord(['general_notes' => 'New note', 'lines' => [
        legacyPurchaseOrderLine(),
        legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'description' => 'To disappear']),
    ]]));

    expect(PurchaseOrder::query()->where('old_id', 800)->value('general_notes'))->toBe("New note\n\nNumero legacy 12 (protocollo 05/03/2025-120)");
});
