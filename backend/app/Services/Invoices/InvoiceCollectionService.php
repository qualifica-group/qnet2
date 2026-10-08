<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\ResidualMode;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record / clear the collection of one installment (spec 0194, D-13; spec
 * 0196): a partial collection closes the installment and moves the residual
 * onto the later ones or onto a new installment, keeping a snapshot of the
 * previous plan so clearing the collection restores it exactly.
 */
final class InvoiceCollectionService
{
    public function __construct(
        private readonly InvoiceAmountCalculator $amounts,
        private readonly InstallmentRebalancer $rebalancer,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, string>  $payload  collected_amount, collected_at, residual_mode?, residual_due_date?
     */
    public function record(InvoiceInstallment $installment, array $payload): Invoice
    {
        return DB::transaction(function () use ($installment, $payload): Invoice {
            // Step 1: serialize on the invoice and refuse a second collection
            [$invoice, $current] = $this->lock($installment);

            if ($this->isCollected($current)) {
                abort(409, 'This installment is already collected: clear the collection first.');
            }

            // Step 2: a full collection closes the installment, a partial one redistributes the residual
            $collected = bcadd($this->amounts->normalize($payload['collected_amount']), '0.005', 2);
            $attributes = ['collected_amount' => $collected, 'collected_at' => $payload['collected_at']];

            if (bccomp($collected, (string) $current->amount, 2) >= 0) {
                $current->update($attributes);
            } else {
                $this->recordPartial($invoice, $current, $attributes, $payload);
            }

            return $this->invoices->detail($invoice);
        });
    }

    public function clear(InvoiceInstallment $installment): Invoice
    {
        return DB::transaction(function () use ($installment): Invoice {
            // Step 1: serialize on the invoice
            [$invoice, $current] = $this->lock($installment);

            // Step 2: restore the previous plan when still possible, else clear only the collection
            $rows = $this->restorable($invoice, $current);

            if ($rows === null) {
                $current->update(['collected_amount' => null, 'collected_at' => null, 'redistribution_snapshot' => null]);
            } else {
                $this->restore($current, $rows);
            }

            return $this->invoices->detail($invoice);
        });
    }

    /**
     * @return array{0: Invoice, 1: InvoiceInstallment}
     */
    private function lock(InvoiceInstallment $installment): array
    {
        $invoice = Invoice::query()->whereKey($installment->invoice_id)->lockForUpdate()->firstOrFail();

        return [$invoice, InvoiceInstallment::query()->whereKey($installment->getKey())->firstOrFail()];
    }

    private function isCollected(InvoiceInstallment $installment): bool
    {
        return bccomp((string) ($installment->collected_amount ?? '0'), '0', 2) > 0;
    }

    /**
     * @param  array<string, string>  $attributes  collected_amount, collected_at
     * @param  array<string, string>  $payload
     */
    private function recordPartial(Invoice $invoice, InvoiceInstallment $current, array $attributes, array $payload): void
    {
        $mode = ResidualMode::tryFrom($payload['residual_mode'] ?? '')
            ?? throw ValidationException::withMessages(['residual_mode' => ['The residual mode is required for a partial collection.']]);
        $residual = bcsub((string) $current->amount, $attributes['collected_amount'], 2);
        $following = $invoice->installments()->where('sequence', '>', $current->sequence)->orderBy('sequence')->get();
        $createdId = null;

        // The snapshot is read before any row is touched: the models are the same instances
        if ($mode === ResidualMode::Spread) {
            $targets = $following->filter(fn (InvoiceInstallment $row): bool => ! $this->isCollected($row))->values();

            if ($targets->isEmpty()) {
                throw ValidationException::withMessages(['residual_mode' => ['There are no later open installments to spread the residual on.']]);
            }

            $entries = $this->entries([$current, ...$targets->all()]);
            $this->spread($targets, $residual);
        } else {
            $entries = $this->entries([$current, ...$following->all()]);
            $this->shiftSequences($following);
            $createdId = $invoice->installments()->create([
                'sequence' => $current->sequence + 1,
                'due_date' => $payload['residual_due_date'],
                'amount' => $residual,
                'payment_method_code' => $current->payment_method_code,
            ])->id;
        }

        $snapshot = ['installments' => $entries, 'created_installment_id' => $createdId];
        $current->update($attributes + ['amount' => $attributes['collected_amount'], 'redistribution_snapshot' => $snapshot]);
    }

    /**
     * @param  Collection<int, InvoiceInstallment>  $targets
     */
    private function spread(Collection $targets, string $residual): void
    {
        $shares = $this->rebalancer->equalParts($residual, $targets->count());

        foreach ($targets as $index => $row) {
            $row->update(['amount' => bcadd((string) $row->amount, $shares[$index], 2)]);
        }
    }

    /**
     * Move the installments one position forward keeping (invoice_id, sequence)
     * unique at every step: the farthest row moves first (D-13).
     *
     * @param  Collection<int, InvoiceInstallment>  $rows
     */
    private function shiftSequences(Collection $rows): void
    {
        foreach ($rows->sortByDesc('sequence') as $row) {
            $row->update(['sequence' => $row->sequence + 1]);
        }
    }

    /**
     * @param  array<int, InvoiceInstallment>  $rows
     * @return array<int, array{id: int, sequence: int, amount: string}>
     */
    private function entries(array $rows): array
    {
        return array_map(fn (InvoiceInstallment $row): array => [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'amount' => (string) $row->amount,
        ], $rows);
    }

    /**
     * The snapshot rows (created installment included) keyed by id, or null
     * when there is nothing (or no longer anything) to restore. A touched
     * installment collected afterwards blocks the clearing (D-10).
     *
     * @return Collection<int, InvoiceInstallment>|null
     */
    private function restorable(Invoice $invoice, InvoiceInstallment $current): ?Collection
    {
        $snapshot = $current->redistribution_snapshot;

        if (! is_array($snapshot) || ! isset($snapshot['installments'])) {
            return null;
        }

        $createdId = $snapshot['created_installment_id'] ?? null;
        $ids = array_column($snapshot['installments'], 'id');
        $expected = $createdId === null ? $ids : [...$ids, $createdId];
        $rows = $invoice->installments()->whereKey($expected)->get()->keyBy('id');

        if ($rows->count() !== count($expected)) {
            return null;
        }

        // A created installment blocks on its own; spread ones block on any other touched row
        $blockers = $createdId === null ? $rows->except($current->id) : $rows->only([$createdId]);

        if ($blockers->contains(fn (InvoiceInstallment $row): bool => $this->isCollected($row))) {
            abort(409, 'Clear the later collections first.');
        }

        return $rows;
    }

    /**
     * @param  Collection<int, InvoiceInstallment>  $rows
     */
    private function restore(InvoiceInstallment $current, Collection $rows): void
    {
        $snapshot = $current->redistribution_snapshot;
        $snapshot['created_installment_id'] === null || $rows->get($snapshot['created_installment_id'])->delete();

        foreach (collect($snapshot['installments'])->sortBy('sequence') as $entry) {
            $rows->get($entry['id'])->update(['sequence' => $entry['sequence'], 'amount' => $entry['amount']]);
        }

        $current->refresh()->update(['collected_amount' => null, 'collected_at' => null, 'redistribution_snapshot' => null]);
    }
}
