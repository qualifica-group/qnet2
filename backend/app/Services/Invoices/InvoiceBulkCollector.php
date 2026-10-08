<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\ResidualMode;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Collects several installments of one customer at once (spec 0198): one
 * collection date, all or nothing. Every row goes through
 * InvoiceCollectionService::record, so a partial amount closes the installment
 * and creates the residual one (spec 0196 D-6) dated as the original due date.
 */
final class InvoiceBulkCollector
{
    public function __construct(
        private readonly InvoiceCollectionService $collections,
        private readonly InvoiceAmountCalculator $amounts,
    ) {}

    /**
     * @param  array<int, array{installment_id: int, collected_amount: string}>  $items
     * @return array{collected_count: int, residual_count: int}
     */
    public function handle(User $actor, array $items, string $collectedAt): array
    {
        return DB::transaction(function () use ($actor, $items, $collectedAt): array {
            // Step 1: lock the invoices involved and read the selected installments
            $installments = $this->lockedInstallments(array_column($items, 'installment_id'));

            // Step 2: authorize every invoice and refuse a mixed or invalid selection
            $this->authorize($actor, $installments);
            $this->assertSameCustomer($installments);
            $this->assertCollectable($items, $installments);

            // Step 3: record each row; a partial one keeps its residual on the original due date
            $residuals = 0;

            foreach ($items as $item) {
                $installment = $installments->get($item['installment_id']);
                $payload = ['collected_amount' => $item['collected_amount'], 'collected_at' => $collectedAt];

                if ($this->isPartial($item['collected_amount'], $installment)) {
                    $payload += [
                        'residual_mode' => ResidualMode::NewInstallment->value,
                        'residual_due_date' => $installment->due_date->toDateString(),
                    ];
                    $residuals++;
                }

                $this->collections->record($installment, $payload);
            }

            return ['collected_count' => count($items), 'residual_count' => $residuals];
        });
    }

    /**
     * Invoices are locked in id order so two concurrent bulk collections can not deadlock.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, InvoiceInstallment>
     */
    private function lockedInstallments(array $ids): Collection
    {
        $invoiceIds = InvoiceInstallment::query()->whereKey($ids)->distinct()->pluck('invoice_id');
        Invoice::query()->whereKey($invoiceIds)->orderBy('id')->lockForUpdate()->get();

        return InvoiceInstallment::query()->whereKey($ids)->with('invoice')->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, InvoiceInstallment>  $installments
     */
    private function authorize(User $actor, Collection $installments): void
    {
        foreach ($installments->pluck('invoice')->unique('id') as $invoice) {
            Gate::forUser($actor)->authorize('collect', $invoice);
        }
    }

    /**
     * @param  Collection<int, InvoiceInstallment>  $installments
     */
    private function assertSameCustomer(Collection $installments): void
    {
        if ($installments->pluck('invoice.customer_registry_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['items' => ['The installments must belong to the same customer.']]);
        }
    }

    /**
     * Every invalid row is reported at once, keyed by its position in the request.
     *
     * @param  array<int, array{installment_id: int, collected_amount: string}>  $items
     * @param  Collection<int, InvoiceInstallment>  $installments
     */
    private function assertCollectable(array $items, Collection $installments): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            $installment = $installments->get($item['installment_id']);

            if (bccomp((string) ($installment->collected_amount ?? '0'), '0', 2) > 0) {
                $errors["items.{$index}.installment_id"] = ['This installment is already collected.'];
            } elseif (bccomp($this->rounded($item['collected_amount']), (string) $installment->amount, 2) > 0) {
                $errors["items.{$index}.collected_amount"] = ['The collected amount cannot exceed the installment amount.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isPartial(string $amount, InvoiceInstallment $installment): bool
    {
        return bccomp($this->rounded($amount), (string) $installment->amount, 2) < 0;
    }

    /**
     * Same cent rounding as InvoiceCollectionService::record, so both agree on "partial".
     */
    private function rounded(string $amount): string
    {
        return bcadd($this->amounts->normalize($amount), '0.005', 2);
    }
}
