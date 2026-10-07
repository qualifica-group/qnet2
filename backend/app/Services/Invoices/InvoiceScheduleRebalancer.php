<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Persistence side of the rebalancing (spec 0196): reads the schedule of an
 * invoice, asks the pure InstallmentRebalancer for the new plan and writes it.
 */
final class InvoiceScheduleRebalancer
{
    public function __construct(private readonly InstallmentRebalancer $rebalancer) {}

    public function hasCollections(Invoice $invoice): bool
    {
        return $invoice->installments()->where('collected_amount', '>', 0)->exists();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, deleted_ids: array<int, int>}
     */
    public function plan(Invoice $invoice, string $newTotal, string $errorField = 'lines'): array
    {
        $current = $invoice->installments()->orderBy('sequence')->get()->map(fn (InvoiceInstallment $row): array => [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'due_date' => CarbonImmutable::parse($row->due_date),
            'amount' => (string) $row->amount,
            'payment_method_code' => $row->payment_method_code,
            'collected_amount' => $row->collected_amount === null ? null : (string) $row->collected_amount,
        ])->all();

        return $this->rebalancer->rebalance($current, $newTotal, $errorField);
    }

    /**
     * Rows for the editor preview of the rebalanced plan.
     *
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null, collected_amount: string|null, locked: bool}>
     */
    public function preview(Invoice $invoice, string $newTotal): array
    {
        return $this->previewRows($this->plan($invoice, $newTotal, 'total_amount')['rows']);
    }

    /**
     * Contract rows of the preview from calculator rows (no collection) or plan rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null, collected_amount: string|null, locked: bool}>
     */
    public function previewRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'sequence' => $row['sequence'],
            'due_date' => $row['due_date']->toDateString(),
            'amount' => $row['amount'],
            'payment_method_code' => $row['payment_method_code'],
            'collected_amount' => ($row['locked'] ?? false) ? $row['collected_amount'] : null,
            'locked' => $row['locked'] ?? false,
        ], $rows);
    }

    /**
     * Once money was collected the fields the schedule hangs on are frozen (D-1).
     *
     * @param  array<string, mixed>  $data
     */
    public function assertFrozenFields(Invoice $invoice, array $data): void
    {
        $changed = [
            'document_date' => CarbonImmutable::parse($data['document_date'])->toDateString() !== $invoice->document_date->toDateString(),
            'payment_method_id' => (int) $data['payment_method_id'] !== $invoice->payment_method_id,
            'customer_registry_id' => (int) $data['customer_registry_id'] !== $invoice->customer_registry_id,
        ];

        $errors = array_map(fn (): array => ['This field cannot be changed once installments have been collected.'], array_filter($changed));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Write a plan and drop every redistribution snapshot (D-10): after a
     * rebalancing the previous plan can no longer be restored.
     *
     * @param  array{rows: array<int, array<string, mixed>>, deleted_ids: array<int, int>}  $plan
     */
    public function apply(Invoice $invoice, array $plan): void
    {
        InvoiceInstallment::query()->whereKey($plan['deleted_ids'])->delete();

        foreach ($plan['rows'] as $row) {
            if ($row['id'] === null) {
                $invoice->installments()->create([
                    'sequence' => $row['sequence'],
                    'due_date' => $row['due_date']->toDateString(),
                    'amount' => $row['amount'],
                    'payment_method_code' => $row['payment_method_code'],
                ]);
            } else {
                InvoiceInstallment::query()->whereKey($row['id'])->update(['amount' => $row['amount']]);
            }
        }

        $invoice->installments()->update(['redistribution_snapshot' => null]);
    }
}
