<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\ResidualMode;
use App\Http\Resources\PurchaseInvoiceInstallmentResource;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\Invoices\InstallmentRebalancer;
use App\Services\Invoices\InvoiceAmountCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Record / clear the payment of one installment (spec 0211, D-5/D-6): mirror of
 * the active InvoiceCollectionService. A partial payment closes the installment
 * and moves the residual onto the later ones or onto a new installment, keeping
 * a snapshot of the previous plan so clearing the payment restores it exactly.
 * Everything runs under a pessimistic lock on the document.
 */
final class PurchaseInvoicePaymentService
{
    public function __construct(
        private readonly InvoiceAmountCalculator $amounts,
        private readonly InstallmentRebalancer $rebalancer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  paid_amount, paid_at, financial_account_id?, residual_mode?, residual_due_date?
     */
    public function record(PurchaseInvoiceInstallment $installment, array $payload): PurchaseInvoiceInstallment
    {
        return DB::transaction(function () use ($installment, $payload): PurchaseInvoiceInstallment {
            // Step 1: serialize on the document and refuse a second payment
            [$invoice, $current] = $this->lock($installment);

            if ($current->isPaid()) {
                abort(409, 'This installment is already paid: clear the payment first.');
            }

            // Step 2: the output account must belong to the document company
            $accountId = $payload['financial_account_id'] ?? null;
            $this->assertAccountAllowed($invoice, $accountId === null ? null : (int) $accountId);

            // Step 3: a full payment closes the installment, a partial one redistributes the residual
            $paid = bcadd($this->amounts->normalize($payload['paid_amount']), '0.005', 2);
            $attributes = ['paid_amount' => $paid, 'paid_at' => $payload['paid_at'], 'financial_account_id' => $accountId];

            if (bccomp($paid, (string) $current->amount, 2) >= 0) {
                $current->update($attributes);
            } else {
                $this->recordPartial($invoice, $current, $attributes, $payload);
            }

            return $this->reload($current);
        });
    }

    public function clear(PurchaseInvoiceInstallment $installment): PurchaseInvoiceInstallment
    {
        return DB::transaction(function () use ($installment): PurchaseInvoiceInstallment {
            // Step 1: serialize on the document
            [$invoice, $current] = $this->lock($installment);

            // Step 2: restore the previous plan when still possible, else clear only the payment
            $rows = $this->restorable($invoice, $current);

            if ($rows === null) {
                $current->update($this->clearedAttributes());
            } else {
                $this->restore($current, $rows);
            }

            return $this->reload($current);
        });
    }

    /**
     * 422 when the account belongs to another company than the document's.
     * An account with no company is valid for every document.
     */
    public function assertAccountAllowed(PurchaseInvoice $invoice, ?int $accountId): void
    {
        if ($accountId === null) {
            return;
        }

        $companyId = FinancialAccount::query()->whereKey($accountId)->value('company_id');

        if ($companyId !== null && (int) $companyId !== (int) $invoice->company_id) {
            throw ValidationException::withMessages(['financial_account_id' => ['The account must belong to the document company.']]);
        }
    }

    /**
     * @return array{0: PurchaseInvoice, 1: PurchaseInvoiceInstallment}
     */
    private function lock(PurchaseInvoiceInstallment $installment): array
    {
        $invoice = PurchaseInvoice::query()->whereKey($installment->purchase_invoice_id)->lockForUpdate()->firstOrFail();

        return [$invoice, PurchaseInvoiceInstallment::query()->whereKey($installment->getKey())->firstOrFail()];
    }

    private function reload(PurchaseInvoiceInstallment $installment): PurchaseInvoiceInstallment
    {
        return PurchaseInvoiceInstallment::query()
            ->with(PurchaseInvoiceInstallmentResource::RELATIONS)
            ->findOrFail($installment->getKey());
    }

    /**
     * @return array<string, null>
     */
    private function clearedAttributes(): array
    {
        return ['paid_amount' => null, 'paid_at' => null, 'financial_account_id' => null, 'redistribution_snapshot' => null];
    }

    /**
     * @param  array<string, mixed>  $attributes  paid_amount, paid_at, financial_account_id
     * @param  array<string, mixed>  $payload
     */
    private function recordPartial(PurchaseInvoice $invoice, PurchaseInvoiceInstallment $current, array $attributes, array $payload): void
    {
        $mode = ResidualMode::tryFrom((string) ($payload['residual_mode'] ?? ''))
            ?? throw ValidationException::withMessages(['residual_mode' => ['The residual mode is required for a partial payment.']]);
        $residual = bcsub((string) $current->amount, $attributes['paid_amount'], 2);
        $following = $invoice->installments()->where('sequence', '>', $current->sequence)->orderBy('sequence')->get();
        $createdId = null;

        // The snapshot is read before any row is touched: the models are the same instances
        if ($mode === ResidualMode::Spread) {
            $targets = $following->filter(fn (PurchaseInvoiceInstallment $row): bool => ! $row->isPaid())->values();

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
        $current->update($attributes + ['amount' => $attributes['paid_amount'], 'redistribution_snapshot' => $snapshot]);
    }

    /**
     * @param  Collection<int, PurchaseInvoiceInstallment>  $targets
     */
    private function spread(Collection $targets, string $residual): void
    {
        $shares = $this->rebalancer->equalParts($residual, $targets->count());

        foreach ($targets as $index => $row) {
            $row->update(['amount' => bcadd((string) $row->amount, $shares[$index], 2)]);
        }
    }

    /**
     * Move the installments one position forward keeping (purchase_invoice_id,
     * sequence) unique at every step: the farthest row moves first.
     *
     * @param  Collection<int, PurchaseInvoiceInstallment>  $rows
     */
    private function shiftSequences(Collection $rows): void
    {
        foreach ($rows->sortByDesc('sequence') as $row) {
            $row->update(['sequence' => $row->sequence + 1]);
        }
    }

    /**
     * @param  array<int, PurchaseInvoiceInstallment>  $rows
     * @return array<int, array{id: int, sequence: int, amount: string}>
     */
    private function entries(array $rows): array
    {
        return array_map(fn (PurchaseInvoiceInstallment $row): array => [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'amount' => (string) $row->amount,
        ], $rows);
    }

    /**
     * The snapshot rows (created installment included) keyed by id, or null
     * when there is nothing (or no longer anything) to restore. A touched
     * installment paid afterwards blocks the clearing.
     *
     * @return Collection<int, PurchaseInvoiceInstallment>|null
     */
    private function restorable(PurchaseInvoice $invoice, PurchaseInvoiceInstallment $current): ?Collection
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

        if ($blockers->contains(fn (PurchaseInvoiceInstallment $row): bool => $row->isPaid())) {
            abort(409, 'Clear the later payments first.');
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PurchaseInvoiceInstallment>  $rows
     */
    private function restore(PurchaseInvoiceInstallment $current, Collection $rows): void
    {
        $snapshot = $current->redistribution_snapshot;
        $snapshot['created_installment_id'] === null || $rows->get($snapshot['created_installment_id'])->delete();

        foreach (collect($snapshot['installments'])->sortBy('sequence') as $entry) {
            $rows->get($entry['id'])->update(['sequence' => $entry['sequence'], 'amount' => $entry['amount']]);
        }

        $current->refresh()->update($this->clearedAttributes());
    }
}
