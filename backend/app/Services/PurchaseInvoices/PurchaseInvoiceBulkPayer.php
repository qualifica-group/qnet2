<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\ResidualMode;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use App\Services\Invoices\InvoiceAmountCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pays several installments of one supplier at once (spec 0211, D-8): one
 * payment date and one output account, all or nothing. Every row goes through
 * PurchaseInvoicePaymentService::record, so a partial amount closes the
 * installment and creates the residual one dated as the original due date.
 */
final class PurchaseInvoiceBulkPayer
{
    public function __construct(
        private readonly PurchaseInvoicePaymentService $payments,
        private readonly InvoiceAmountCalculator $amounts,
    ) {}

    /**
     * @param  array<int, array{installment_id: int, paid_amount: string}>  $items
     * @return array{paid_count: int, residual_count: int}
     */
    public function handle(User $actor, array $items, string $paidAt, ?int $financialAccountId): array
    {
        return DB::transaction(function () use ($actor, $items, $paidAt, $financialAccountId): array {
            // Step 1: lock the documents involved and read the selected installments
            $installments = $this->lockedInstallments(array_column($items, 'installment_id'));

            // Step 2: authorize every document and refuse a mixed or invalid selection
            $this->authorize($actor, $installments);
            $this->assertSameSupplier($installments);
            $this->assertPayable($items, $installments);
            $this->assertAccountAllowed($installments, $financialAccountId);

            // Step 3: pay each row; a partial one keeps its residual on the original due date
            $residuals = 0;

            foreach ($items as $item) {
                $installment = $installments->get($item['installment_id']);
                $payload = ['paid_amount' => $item['paid_amount'], 'paid_at' => $paidAt, 'financial_account_id' => $financialAccountId];

                if ($this->isPartial($item['paid_amount'], $installment)) {
                    $payload += [
                        'residual_mode' => ResidualMode::NewInstallment->value,
                        'residual_due_date' => $installment->due_date->toDateString(),
                    ];
                    $residuals++;
                }

                $this->payments->record($installment, $payload);
            }

            return ['paid_count' => count($items), 'residual_count' => $residuals];
        });
    }

    /**
     * Documents are locked in id order so two concurrent bulk payments can not deadlock.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, PurchaseInvoiceInstallment>
     */
    private function lockedInstallments(array $ids): Collection
    {
        $invoiceIds = PurchaseInvoiceInstallment::query()->whereKey($ids)->distinct()->pluck('purchase_invoice_id');
        PurchaseInvoice::query()->whereKey($invoiceIds)->orderBy('id')->lockForUpdate()->get();

        return PurchaseInvoiceInstallment::query()->whereKey($ids)->with('purchaseInvoice')->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, PurchaseInvoiceInstallment>  $installments
     */
    private function authorize(User $actor, Collection $installments): void
    {
        foreach ($installments->pluck('purchaseInvoice')->unique('id') as $invoice) {
            Gate::forUser($actor)->authorize('pay', $invoice);
        }
    }

    /**
     * @param  Collection<int, PurchaseInvoiceInstallment>  $installments
     */
    private function assertSameSupplier(Collection $installments): void
    {
        if ($installments->pluck('purchaseInvoice.supplier_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['items' => ['The installments must belong to the same supplier.']]);
        }
    }

    /**
     * Every invalid row is reported at once, keyed by its position in the request.
     *
     * @param  array<int, array{installment_id: int, paid_amount: string}>  $items
     * @param  Collection<int, PurchaseInvoiceInstallment>  $installments
     */
    private function assertPayable(array $items, Collection $installments): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            $installment = $installments->get($item['installment_id']);

            if ($installment->isPaid()) {
                $errors["items.{$index}.installment_id"] = ['This installment is already paid.'];
            } elseif (bccomp($this->rounded($item['paid_amount']), (string) $installment->amount, 2) > 0) {
                $errors["items.{$index}.paid_amount"] = ['The paid amount cannot exceed the installment amount.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The single account must suit the company of every document involved.
     *
     * @param  Collection<int, PurchaseInvoiceInstallment>  $installments
     */
    private function assertAccountAllowed(Collection $installments, ?int $financialAccountId): void
    {
        foreach ($installments->pluck('purchaseInvoice')->unique('id') as $invoice) {
            $this->payments->assertAccountAllowed($invoice, $financialAccountId);
        }
    }

    private function isPartial(string $amount, PurchaseInvoiceInstallment $installment): bool
    {
        return bccomp($this->rounded($amount), (string) $installment->amount, 2) < 0;
    }

    /**
     * Same cent rounding as PurchaseInvoicePaymentService::record, so both agree on "partial".
     */
    private function rounded(string $amount): string
    {
        return bcadd($this->amounts->normalize($amount), '0.005', 2);
    }
}
