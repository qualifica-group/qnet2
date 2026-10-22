<?php

use App\Enums\MigrationStatus;
use App\Enums\PurchaseOrderStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Migrations\Support\LegacyPurchaseOrderLineWriter;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderNumberSequence;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Fixtures (legacyPurchaseOrderRecord, legacyPurchaseOrderAnchors, ...) live in
// tests/Helpers/PurchaseOrderMigrationHelpers.php; the report readers
// (legacyTaskWarnings/legacyTaskErrors) in TaskMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    $this->anchors = legacyPurchaseOrderAnchors();
});

function migratedPurchaseOrderWorkOrder(int $oldId): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => $oldId])->save();

    return $workOrder;
}

function migratedPurchaseRequestLine(int $oldId): PurchaseRequestLine
{
    $line = PurchaseRequestLine::factory()->create();
    $line->forceFill(['old_id' => $oldId])->save();

    return $line;
}

it('AC-001 imports a complete legacy ODA with its lines, without log or notification', function () {
    Notification::fake();
    $customer = Registry::factory()->create(['old_id' => 74]);
    $operationalSite = OperationalSite::factory()->create();
    $operationalSite->forceFill(['old_id' => 75])->save();
    $paymentMethod = PaymentMethod::factory()->create();
    $paymentMethod->forceFill(['old_id' => 76])->save();
    $vatRate = VatRate::factory()->create(['old_id' => 77, 'rate' => 22]);
    $workOrder = migratedPurchaseOrderWorkOrder(78);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord([
        'general_notes' => "Deliver to Teverola\n\nEstrema urgenza.",
        'customer_id' => 74,
        'operational_site_id' => 75,
        'payment_method_id' => 76,
        'is_internal_user' => true,
        'sector_notes' => 'EA 37',
        'status' => 'approved',
        'lines' => [
            legacyPurchaseOrderLine(['cost_type_name' => 'Fitti', 'quantity' => 3, 'unit_price' => 10.1, 'vat_rate_id' => 77, 'work_order_id' => 78]),
            legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'quantity' => 1, 'unit_price' => 5, 'work_order_id' => 78]),
        ],
    ])], $actor);

    $order = PurchaseOrder::query()->where('old_id', 800)->sole();
    $line = PurchaseOrderLine::query()->where('old_id', 1500)->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($order->code())->toBe('12/2025')
        ->and($order->order_date->toDateString())->toBe('2025-03-05')
        ->and($order->description)->toBe('Legacy ODA')
        ->and($order->general_notes)->toBe("Deliver to Teverola\n\nEstrema urgenza.")
        ->and($order->supplier_id)->toBe($this->anchors['supplier']->id)
        ->and($order->customer_id)->toBe($customer->id)
        ->and($order->company_id)->toBe($this->anchors['company']->id)
        ->and($order->company_site_id)->toBe($this->anchors['companySite']->id)
        ->and($order->operational_site_id)->toBe($operationalSite->id)
        ->and($order->payment_method_id)->toBe($paymentMethod->id)
        ->and($order->is_internal_user)->toBeTrue()
        ->and($order->sector_notes)->toBe('EA 37')
        ->and($order->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($order->created_by)->toBe($this->anchors['creator']->id)
        ->and($order->work_order_id)->toBe($workOrder->id)
        ->and($order->created_at->toDateTimeString())->toBe('2025-03-05 08:00:00')
        ->and($order->updated_at->toDateTimeString())->toBe('2025-03-09 08:00:00')
        ->and($order->taxable_total)->toBe('35.30')
        ->and($order->vat_total)->toBe('6.67')
        ->and($order->grand_total)->toBe('41.97')
        ->and($line->purchase_order_id)->toBe($order->id)
        ->and($line->cost_type_id)->toBe(CostType::query()->where('name', 'Fitti')->value('id'))
        ->and($line->quantity)->toBe('3.000')
        ->and($line->unit_price)->toBe('10.10')
        ->and($line->vat_rate_id)->toBe($vatRate->id)
        ->and($line->taxable_amount)->toBe('30.30')
        ->and($line->work_order_id)->toBe($workOrder->id)
        ->and($order->receipts()->count())->toBe(0)
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-002 skips an ODA already imported', function () {
    runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord()]);

    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord(['description' => 'Changed'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and(PurchaseOrder::query()->sole()->description)->toBe('Legacy ODA');
});

it('AC-003 fails the row when a required anchor is missing, not migrated or incoherent', function (array $overrides, string $reason) {
    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(PurchaseOrder::query()->count())->toBe(0)
        ->and(PurchaseOrderLine::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'supplier' => [['supplier_id' => null], 'Supplier is required'],
    'company' => [['company_id' => 99], 'Company (legacy id 99) not migrated'],
    'company site' => [['company_site_id' => 99], 'Company site (legacy id 99) not migrated'],
    'creator' => [['creator_user_id' => 99], 'Creator (legacy id 99) not migrated'],
    'cost type' => [['lines' => [legacyPurchaseOrderLine(['cost_type_name' => 'Unknown'])]], "cost type 'Unknown' not found"],
    'status' => [['status' => 'lost'], "Status 'lost' is not valid"],
]);

it('AC-003 fails the row when the company site belongs to another company', function () {
    $otherSite = CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    $otherSite->forceFill(['old_id' => 90])->save();

    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord(['company_site_id' => 90])]);

    expect($run->failed_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain('does not belong to the company');
});

it('AC-004 leaves optional references empty with a warning', function () {
    $alreadyLinked = migratedPurchaseRequestLine(61);
    PurchaseOrderLine::factory()->create(['purchase_request_line_id' => $alreadyLinked->id]);

    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord([
        'customer_id' => 98,
        'operational_site_id' => 97,
        'payment_method_id' => 96,
        'lines' => [
            legacyPurchaseOrderLine(['vat_rate_id' => 95, 'work_order_id' => 94, 'purchase_request_line_id' => 93]),
            legacyPurchaseOrderLine(['id' => 1501, 'position' => 2, 'purchase_request_line_id' => 61]),
        ],
    ])]);

    $order = PurchaseOrder::query()->where('old_id', 800)->sole();
    $lines = $order->lines()->get();

    expect($run->created_rows)->toBe(1)
        ->and($order->customer_id)->toBeNull()
        ->and($order->operational_site_id)->toBeNull()
        ->and($order->payment_method_id)->toBeNull()
        ->and($order->work_order_id)->toBeNull()
        ->and($lines->pluck('vat_rate_id')->filter())->toBeEmpty()
        ->and($lines->pluck('purchase_request_line_id')->filter())->toBeEmpty()
        ->and(legacyTaskWarnings($run))->toHaveCount(7)
        ->and(legacyTaskWarnings($run))->toContain('Line 1501: purchase request line (legacy id 61) already linked to another order, left empty.');
});

it('AC-005 renumbers a number already used past the highest legacy and qnet-2 number', function () {
    runLegacyPurchaseOrdersImport([
        legacyPurchaseOrderRecord(),
        legacyPurchaseOrderRecord(['id' => 801, 'general_notes' => 'Copy of March', 'lines' => [legacyPurchaseOrderLine(['id' => 1501])]]),
    ]);

    $renumbered = PurchaseOrder::query()->where('old_id', 801)->sole();
    $sequence = PurchaseOrderNumberSequence::query()->where('company_id', $this->anchors['company']->id)->where('year', 2025)->sole();

    expect(PurchaseOrder::query()->where('old_id', 800)->sole()->code())->toBe('12/2025')
        ->and($renumbered->code())->toBe('41/2025')
        ->and($renumbered->general_notes)->toBe("Copy of March\n\nNumero legacy 12 (protocollo 05/03/2025-120)")
        ->and($sequence->last_number)->toBe(41);
});

it('AC-006 points the linked RDA line at the order code', function () {
    $requestLine = migratedPurchaseRequestLine(60);

    runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord(['lines' => [legacyPurchaseOrderLine(['purchase_request_line_id' => 60])]])]);

    expect(PurchaseOrderLine::query()->sole()->purchase_request_line_id)->toBe($requestLine->id)
        ->and($requestLine->fresh()->oda_reference)->toBe('12/2025');
});

it('AC-006 leaves the header work order empty when the lines disagree', function () {
    migratedPurchaseOrderWorkOrder(78);
    migratedPurchaseOrderWorkOrder(79);

    runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord(['lines' => [
        legacyPurchaseOrderLine(['work_order_id' => 78]),
        legacyPurchaseOrderLine(['id' => 1501, 'work_order_id' => 79]),
    ]])]);

    expect(PurchaseOrder::query()->sole()->work_order_id)->toBeNull();
});

it('AC-007 receives every quantity of a fulfilled ODA and none of a partially fulfilled one', function () {
    $run = runLegacyPurchaseOrdersImport([
        legacyPurchaseOrderRecord(['status' => 'fulfilled']),
        legacyPurchaseOrderRecord(['id' => 801, 'number' => 13, 'status' => 'partially_fulfilled', 'lines' => [legacyPurchaseOrderLine(['id' => 1501])]]),
    ]);

    $fulfilled = PurchaseOrder::query()->where('old_id', 800)->sole();
    $partial = PurchaseOrder::query()->where('old_id', 801)->sole();
    $receipt = $fulfilled->receipts()->sole();

    expect($fulfilled->status)->toBe(PurchaseOrderStatus::Fulfilled)
        ->and($receipt->receipt_date->toDateString())->toBe('2025-03-09')
        ->and($receipt->notes)->toBe(LegacyPurchaseOrderLineWriter::RECEIPT_NOTE)
        ->and($receipt->created_by)->toBe($this->anchors['creator']->id)
        ->and($receipt->lines()->sole()->quantity)->toBe('2.000')
        ->and($fulfilled->lines()->sole()->received_quantity)->toBe('2.000')
        ->and($partial->status)->toBe(PurchaseOrderStatus::PartiallyFulfilled)
        ->and($partial->receipts()->count())->toBe(0)
        ->and(legacyTaskWarnings($run))->toBe(['Partially fulfilled in the legacy: no receipt is imported.']);
});

it('AC-008 truncates long texts and copies the legacy warnings', function () {
    $run = runLegacyPurchaseOrdersImport([legacyPurchaseOrderRecord([
        'description' => str_repeat('d', 300),
        'sector_notes' => str_repeat('s', 600),
        'warnings' => ['Riga RDA 5 inesistente: collegamento omesso.'],
        'lines' => [legacyPurchaseOrderLine(['description' => str_repeat('l', 600)])],
    ])]);

    $order = PurchaseOrder::query()->sole();

    expect(mb_strlen($order->description))->toBe(255)
        ->and(mb_strlen($order->sector_notes))->toBe(500)
        ->and(mb_strlen($order->lines()->sole()->description))->toBe(500)
        ->and(legacyTaskWarnings($run))->toBe([
            'Riga RDA 5 inesistente: collegamento omesso.',
            'Description truncated to 255 characters.',
            'Sector notes truncated to 500 characters.',
            'Line 1500: description truncated to 500 characters.',
        ]);
});

it('AC-009 registers the source in the phase after purchase requests with its dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('purchase-orders');

    expect(MigrationOrder::phaseOf('purchase-orders'))->toBe(MigrationOrder::phaseOf('purchase-requests') + 1)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([
            ['users', true], ['companies', true], ['company-sites', true], ['registries', true],
            ['operational-sites', false], ['payment-methods', false], ['vat-rates', false], ['work-orders', false],
            ['purchase-requests', false],
        ]);
});
