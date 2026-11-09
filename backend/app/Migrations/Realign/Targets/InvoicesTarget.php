<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\InvoiceType;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\InvoiceScheduleAligner;
use App\Migrations\Realign\Support\PurchaseDocumentTarget;
use App\Migrations\Realign\Support\PurchaseLegacyReferences;
use App\Migrations\Sources\InvoicesSource;
use App\Migrations\Support\LegacyInvoiceWriter;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Active invoices and proformas (spec 0375 D-11, D-14): notes, external number and
 * date, the schedule and its collections. Lines and amounts are never written:
 * when they differ the report says so.
 */
final class InvoicesTarget extends PurchaseDocumentTarget
{
    private const int MONEY_SCALE = 2;

    private const string KEY_SEPARATOR = ':';

    private const array HEADER_COLUMNS = ['notes', 'internal_note', 'external_number', 'external_date', 'net_amount', 'vat_amount', 'total_amount'];

    private const array INSTALLMENT_COLUMNS = ['due_date', 'amount', 'payment_method_code', 'collected_amount', 'collected_at'];

    public function __construct(
        PurchaseLegacyReferences $references,
        private readonly InvoicesSource $invoiceSource,
        private readonly LegacyInvoiceWriter $writer,
        private readonly InvoiceScheduleAligner $scheduleAligner,
    ) {
        parent::__construct($references);
    }

    public function source(): string
    {
        return 'invoices';
    }

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    public function legacyKey(array $legacy): string
    {
        return ($legacy['source'] ?? '').self::KEY_SEPARATOR.($legacy['id'] ?? '');
    }

    public function findMany(array $legacyKeys): array
    {
        $idsBySource = [];

        foreach ($legacyKeys as $legacyKey) {
            [$source, $id] = explode(self::KEY_SEPARATOR, $legacyKey, 2);
            $idsBySource[$source][] = $id;
        }

        $found = [];

        foreach ($idsBySource as $source => $ids) {
            foreach (Invoice::query()->where('old_source', $source)->whereIn('old_id', $ids)->get() as $invoice) {
                $found[$source.self::KEY_SEPARATOR.$invoice->old_id] = $invoice;
            }
        }

        return $found;
    }

    public function label(Model $record): string
    {
        return $record->getAttribute('number').'/'.$record->getAttribute('year');
    }

    /**
     * The installments count: collections are logged on them, not on the document.
     */
    public function touchSubjects(Model $record): array
    {
        return [
            [Invoice::class, $record->getKey()],
            [InvoiceInstallment::class, InvoiceInstallment::query()->where('invoice_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var Invoice $record */
        return [
            ...$this->columns($record, self::HEADER_COLUMNS),
            ...$this->rowFields($record->installments()->orderBy('sequence')->get(), 'installment', 'sequence', self::INSTALLMENT_COLUMNS),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var Invoice $record */
        $warnings = [];

        // Step 1: notes and external reference.
        $record->forceFill($this->header($record, $legacy, $warnings));

        // Step 2: the schedule and its collections.
        $scheduleChanged = $this->scheduleAligner->align(
            $record->installments()->orderBy('sequence')->get(),
            $this->legacySchedule($record, $legacy, $warnings),
            'collected_amount',
            'collected_at',
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
    private function header(Invoice $record, array $legacy, array &$warnings): array
    {
        $isInvoice = $record->type === InvoiceType::Invoice;

        return [
            'notes' => $this->references->notesKeepingNumberReference($record->notes, $this->references->text($legacy['notes'] ?? null)),
            'internal_note' => $this->references->text($legacy['internal_note'] ?? null),
            'external_number' => $isInvoice ? $this->invoiceSource->externalNumber($legacy['external_number'] ?? null) : null,
            'external_date' => $isInvoice ? $this->invoiceSource->externalDate($legacy['external_date'] ?? null, CarbonImmutable::parse($record->document_date), $warnings) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function legacySchedule(Invoice $record, array $legacy, array &$warnings): array
    {
        $installments = (array) ($legacy['installments'] ?? []);

        return $installments === [] ? [] : array_values($this->writer->legacy($installments, (string) $record->total_amount, $warnings));
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function warnOnDifferentAmounts(Invoice $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $ignored = [];

        try {
            $computed = $this->writer->computeLines((array) ($legacy['lines'] ?? []), $ignored);
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
