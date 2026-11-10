<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\InvoiceScheduleAligner;
use App\Migrations\Realign\Support\PurchaseDocumentTarget;
use App\Migrations\Realign\Support\PurchaseLegacyReferences;
use App\Migrations\Sources\PurchaseInvoicesSource;
use App\Migrations\Support\LegacyPurchaseInvoiceWriter;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Supplier invoices (spec 0375 D-11, D-14): the supplier's number and date, the
 * schedule and its payment. Lines and amounts are never written: when they differ
 * the report says so. The legacy sends no notes for these documents.
 */
final class PurchaseInvoicesTarget extends PurchaseDocumentTarget
{
    private const int MONEY_SCALE = 2;

    private const array HEADER_COLUMNS = ['document_number', 'document_date', 'net_amount', 'vat_amount', 'total_amount'];

    private const array INSTALLMENT_COLUMNS = ['due_date', 'amount', 'payment_method_code', 'paid_amount', 'paid_at'];

    public function __construct(
        PurchaseLegacyReferences $references,
        private readonly PurchaseInvoicesSource $invoiceSource,
        private readonly LegacyPurchaseInvoiceWriter $writer,
        private readonly InvoiceScheduleAligner $scheduleAligner,
    ) {
        parent::__construct($references);
    }

    public function source(): string
    {
        return 'purchase-invoices';
    }

    protected function modelClass(): string
    {
        return PurchaseInvoice::class;
    }

    public function label(Model $record): string
    {
        return (string) ($record->getAttribute('document_number') ?? $record->getKey());
    }

    /**
     * The installments count: payments are logged on them, not on the invoice.
     */
    public function touchSubjects(Model $record): array
    {
        return [
            [PurchaseInvoice::class, $record->getKey()],
            [PurchaseInvoiceInstallment::class, PurchaseInvoiceInstallment::query()->where('purchase_invoice_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var PurchaseInvoice $record */
        return [
            ...$this->columns($record, self::HEADER_COLUMNS),
            ...$this->rowFields($record->installments()->orderBy('sequence')->get(), 'installment', 'sequence', self::INSTALLMENT_COLUMNS),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var PurchaseInvoice $record */
        $warnings = [];

        // Step 1: the supplier's number and date (`saveQuietly` skips the year hook).
        $record->forceFill($this->documentReference($record, $legacy, $warnings, $notes));

        // Step 2: the schedule and its payment.
        $scheduleChanged = $this->scheduleAligner->align(
            $record->installments()->orderBy('sequence')->get(),
            $this->legacySchedule($record, $legacy, $warnings),
            'paid_amount',
            'paid_at',
            $notes,
        );

        // Step 3: lines and amounts are only compared (D-14).
        $this->warnOnDifferentAmounts($record, $legacy, $notes);

        // Step 4: one quiet save.
        $this->finish($record, $scheduleChanged, $legacy, $warnings, $notes);
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function documentReference(PurchaseInvoice $record, array $legacy, array &$warnings, LegacyRealignNotes $notes): array
    {
        $number = $this->references->text($legacy['document_number'] ?? null);
        $date = $this->references->timestamp($legacy['document_date'] ?? null);

        if ($number === null || $date === null) {
            return [];
        }

        if (mb_strlen($number) > PurchaseInvoicesSource::NUMBER_MAX) {
            $warnings[] = "Number '{$number}' longer than ".PurchaseInvoicesSource::NUMBER_MAX.' characters; kept.';

            return [];
        }

        $year = $record->isRegistered() ? $date->year : null;

        if ($this->isNumberTaken($record, $number, $year)) {
            $notes->warn("Number '{$number}' already used by the supplier in {$date->year}; number and date kept.");

            return [];
        }

        return ['document_number' => $number, 'document_date' => $date->toDateString(), 'document_year' => $year];
    }

    private function isNumberTaken(PurchaseInvoice $record, string $number, ?int $year): bool
    {
        return PurchaseInvoice::query()
            ->where('supplier_id', $record->supplier_id)
            ->where('document_number', $number)
            ->where('document_year', $year)
            ->whereKeyNot($record->getKey())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function legacySchedule(PurchaseInvoice $record, array $legacy, array &$warnings): array
    {
        $installments = (array) ($legacy['installments'] ?? []);

        if ($installments === []) {
            return [];
        }

        $paidAt = ($legacy['is_paid'] ?? false) ? $this->invoiceSource->paidAt($legacy) : null;
        $schedule = $this->writer->legacy($installments, (string) $record->total_amount, $warnings);

        return array_values(array_map(
            static fn (array $row): array => $paidAt === null ? $row : $row + ['paid_amount' => $row['amount'], 'paid_at' => $paidAt],
            $schedule,
        ));
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function warnOnDifferentAmounts(PurchaseInvoice $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $orderIds = $record->purchaseOrders()->pluck('purchase_orders.id')->map(intval(...))->all();
        $ignored = [];

        try {
            $computed = $this->writer->computeLines((array) ($legacy['lines'] ?? []), $orderIds, $ignored);
        } catch (RuntimeException $exception) {
            $notes->warn('Legacy lines cannot be compared: '.$exception->getMessage());

            return;
        }

        $sameTotals = bccomp($computed['net'], (string) $record->net_amount, self::MONEY_SCALE) === 0
            && bccomp($computed['vat'], (string) $record->vat_amount, self::MONEY_SCALE) === 0
            && bccomp($computed['total'], (string) $record->total_amount, self::MONEY_SCALE) === 0;

        if (! $sameTotals || count($computed['lines']) !== $record->lines()->count()) {
            $notes->warn("Legacy lines or amounts differ (legacy total {$computed['total']}, qnet-2 {$record->total_amount}); not updated.");
        }
    }
}
