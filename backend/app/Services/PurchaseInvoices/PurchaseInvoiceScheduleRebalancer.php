<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\Invoices\InstallmentRebalancer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Persistence side of the rebalancing of a payable document (spec 0250, D-4):
 * the twin of InvoiceScheduleRebalancer on `paid_amount` (declared copy, the
 * deduplication with the active side is planned for wave 4). The pure plan is
 * InstallmentRebalancer's.
 */
final class PurchaseInvoiceScheduleRebalancer
{
    /** Fields the schedule hangs on, frozen once a payment is recorded. */
    private const array FROZEN_FIELDS = ['document_date', 'payment_method_id'];

    public function __construct(private readonly InstallmentRebalancer $rebalancer) {}

    public function hasPayments(PurchaseInvoice $document): bool
    {
        return $document->hasPayments();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, deleted_ids: array<int, int>}
     */
    public function plan(PurchaseInvoice $document, string $newTotal, string $errorField = 'lines'): array
    {
        $current = $document->installments()->orderBy('sequence')->get()->map(fn (PurchaseInvoiceInstallment $row): array => [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'due_date' => CarbonImmutable::parse($row->due_date),
            'amount' => (string) $row->amount,
            'payment_method_code' => $row->payment_method_code,
            'collected_amount' => $row->paid_amount === null ? null : (string) $row->paid_amount,
        ])->all();

        return $this->rebalancer->rebalance($current, $newTotal, $errorField);
    }

    /**
     * Rows for the editor preview of the rebalanced plan.
     *
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null, paid_amount: string|null, locked: bool}>
     */
    public function preview(PurchaseInvoice $document, string $newTotal): array
    {
        return $this->previewRows($this->plan($document, $newTotal, 'total_amount')['rows']);
    }

    /**
     * Contract rows of the preview from planner rows (no payment) or plan rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null, paid_amount: string|null, locked: bool}>
     */
    public function previewRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'sequence' => $row['sequence'],
            'due_date' => $row['due_date']->toDateString(),
            'amount' => $row['amount'],
            'payment_method_code' => $row['payment_method_code'],
            'paid_amount' => ($row['locked'] ?? false) ? $row['collected_amount'] : null,
            'locked' => $row['locked'] ?? false,
        ], $rows);
    }

    /**
     * Once money was paid the fields the schedule hangs on are frozen (D-2).
     *
     * @param  array<string, mixed>  $data
     */
    public function assertFrozenFields(PurchaseInvoice $document, array $data): void
    {
        $changed = [
            'document_date' => CarbonImmutable::parse($data['document_date'])->toDateString() !== $document->document_date->toDateString(),
            'payment_method_id' => (int) $data['payment_method_id'] !== $document->payment_method_id,
        ];

        $errors = array_map(fn (): array => [__('purchase_invoices.payments_frozen')], array_filter($changed));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Write a plan and drop every redistribution snapshot: after a rebalancing
     * the previous plan can no longer be restored.
     *
     * @param  array{rows: array<int, array<string, mixed>>, deleted_ids: array<int, int>}  $plan
     */
    public function apply(PurchaseInvoice $document, array $plan): void
    {
        PurchaseInvoiceInstallment::query()->whereKey($plan['deleted_ids'])->delete();

        foreach ($plan['rows'] as $row) {
            if ($row['id'] === null) {
                $document->installments()->create([
                    'sequence' => $row['sequence'],
                    'due_date' => $row['due_date']->toDateString(),
                    'amount' => $row['amount'],
                    'payment_method_code' => $row['payment_method_code'],
                ]);
            } else {
                PurchaseInvoiceInstallment::query()->whereKey($row['id'])->update(['amount' => $row['amount']]);
            }
        }

        $document->installments()->update(['redistribution_snapshot' => null]);
    }
}
