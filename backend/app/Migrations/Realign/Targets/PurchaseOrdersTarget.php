<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\PurchaseOrderStatus;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\PurchaseDocumentTarget;
use App\Migrations\Realign\Support\PurchaseLegacyReferences;
use App\Migrations\Realign\Support\PurchaseOrderLineAligner;
use App\Migrations\Sources\PurchaseOrdersSource;
use App\Migrations\Support\LegacyPurchaseOrderLineWriter;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * ODA (spec 0375 D-11): header, status and lines. Number, year, company and creator
 * stay fixed; an order that already has receipts gets no automatic one.
 */
final class PurchaseOrdersTarget extends PurchaseDocumentTarget
{
    private const array HEADER_COLUMNS = [
        'order_date', 'description', 'general_notes', 'supplier_id', 'payment_method_id', 'is_internal_user', 'customer_id',
        'operational_site_id', 'sector_notes', 'work_order_id', 'status', 'taxable_total', 'vat_total', 'grand_total',
    ];

    private const array LINE_COLUMNS = [
        'position', 'description', 'cost_type_id', 'quantity', 'unit_price', 'vat_rate_id', 'work_order_id',
        'taxable_amount', 'vat_amount', 'total_amount', 'purchase_request_line_id',
    ];

    public function __construct(
        PurchaseLegacyReferences $references,
        private readonly PurchaseOrdersSource $orderSource,
        private readonly PurchaseOrderLineAligner $lineAligner,
        private readonly LegacyPurchaseOrderLineWriter $lineWriter,
    ) {
        parent::__construct($references);
    }

    public function source(): string
    {
        return 'purchase-orders';
    }

    protected function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    public function label(Model $record): string
    {
        /** @var PurchaseOrder $record */
        return $record->code();
    }

    /**
     * Lines and receipts count: they are logged on themselves, not on the order.
     */
    public function touchSubjects(Model $record): array
    {
        return [
            [PurchaseOrder::class, $record->getKey()],
            [PurchaseOrderLine::class, PurchaseOrderLine::query()->where('purchase_order_id', $record->getKey())->select('id')],
            [PurchaseOrderReceipt::class, PurchaseOrderReceipt::query()->where('purchase_order_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var PurchaseOrder $record */
        return [
            ...$this->columns($record, self::HEADER_COLUMNS),
            'receipts' => $record->receipts()->count(),
            ...$this->rowFields($record->lines()->orderBy('id')->get(), 'line', 'old_id', self::LINE_COLUMNS),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var PurchaseOrder $record */
        $warnings = [];
        $status = PurchaseOrderStatus::tryFrom((string) ($legacy['status'] ?? ''))
            ?? throw new RuntimeException("Status '".($legacy['status'] ?? '')."' is not valid.");

        // Step 1: the lines and their totals, on their own save.
        $linesChanged = $this->lineAligner->align($record, (array) ($legacy['lines'] ?? []), $warnings, $notes);

        // Step 2: the header and the status.
        $record->forceFill($this->header($record, $legacy, $status, $warnings, $notes));

        // Step 3: the receipt of an order that becomes fulfilled, and only if none exists.
        $received = $this->receiveWhenFulfilled($record, $status, $legacy, $notes);

        // Step 4: one quiet save.
        $this->finish($record, $linesChanged || $received, $legacy, $warnings, $notes);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function header(PurchaseOrder $record, array $legacy, PurchaseOrderStatus $status, array &$warnings, LegacyRealignNotes $notes): array
    {
        return [
            'order_date' => $this->orderDate($record, $legacy, $notes),
            'description' => $this->references->capped($legacy['description'] ?? null, PurchaseOrdersSource::DESCRIPTION_MAX, 'Description', $warnings) ?? $record->description,
            'general_notes' => $this->references->notesKeepingNumberReference($record->general_notes, $this->references->text($legacy['general_notes'] ?? null)),
            'supplier_id' => $this->references->required(Registry::class, $legacy['supplier_id'] ?? null, 'Supplier'),
            'payment_method_id' => $this->references->optionalOrCurrent(PaymentMethod::class, $legacy['payment_method_id'] ?? null, $record->payment_method_id, 'Payment method', $warnings),
            'is_internal_user' => (bool) ($legacy['is_internal_user'] ?? false),
            'customer_id' => $this->references->optionalOrCurrent(Registry::class, $legacy['customer_id'] ?? null, $record->customer_id, 'Customer', $warnings),
            'operational_site_id' => $this->references->optionalOrCurrent(OperationalSite::class, $legacy['operational_site_id'] ?? null, $record->operational_site_id, 'Operational site', $warnings),
            'sector_notes' => $this->references->capped($legacy['sector_notes'] ?? null, PurchaseOrdersSource::SECTOR_NOTES_MAX, 'Sector notes', $warnings),
            'work_order_id' => $this->orderSource->sharedWorkOrderId($record),
            'status' => $this->statusToWrite($record, $status, $notes),
        ];
    }

    /**
     * Receipts and invoices move an order to fulfilled on qnet-2 while the legacy keeps
     * its own status for ever: going back would undo them (D-11).
     */
    private function statusToWrite(PurchaseOrder $record, PurchaseOrderStatus $status, LegacyRealignNotes $notes): PurchaseOrderStatus
    {
        $wasReceived = in_array($record->status, [PurchaseOrderStatus::PartiallyFulfilled, PurchaseOrderStatus::Fulfilled], true);
        $goesBack = $status !== PurchaseOrderStatus::Fulfilled && $status !== $record->status;

        if ($wasReceived && $goesBack && ($record->receipts()->exists() || $record->purchaseInvoices()->exists())) {
            $notes->warn("Status '{$status->value}' in the legacy, but the order has receipts or invoices on qnet-2; kept '{$record->status->value}'.");

            return $record->status;
        }

        return $status;
    }

    /**
     * The number is unique per year: a date moving to another year would break it.
     *
     * @param  array<string, mixed>  $legacy
     */
    private function orderDate(PurchaseOrder $record, array $legacy, LegacyRealignNotes $notes): mixed
    {
        $date = $this->references->timestamp($legacy['order_date'] ?? null);

        if ($date === null) {
            return $record->order_date;
        }

        if ($date->year !== $record->year) {
            $notes->warn("Legacy order date {$date->toDateString()} is in another year than the order number; kept.");

            return $record->order_date;
        }

        return $date->toDateString();
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function receiveWhenFulfilled(PurchaseOrder $record, PurchaseOrderStatus $status, array $legacy, LegacyRealignNotes $notes): bool
    {
        if ($status !== PurchaseOrderStatus::Fulfilled || $record->getOriginal('status') === PurchaseOrderStatus::Fulfilled) {
            return false;
        }

        if ($record->receipts()->exists() || $record->lines()->where('received_quantity', '>', 0)->exists()) {
            $notes->warn('Fulfilled in the legacy, but the order already has receipts on qnet-2: none is generated.');

            return false;
        }

        $receiptDate = $this->references->timestamp($legacy['updated_at'] ?? null)?->toDateString() ?? $record->order_date->toDateString();
        $this->lineWriter->receiveAll($record, $receiptDate);

        return true;
    }
}
