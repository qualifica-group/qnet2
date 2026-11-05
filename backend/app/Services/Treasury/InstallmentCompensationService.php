<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\InvoiceType;
use App\Enums\PurchaseInvoiceType;
use App\Enums\ResidualMode;
use App\Models\InstallmentCompensation;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use App\Services\Invoices\InvoiceCollectionService;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compensates an open receivable installment against an open payable one of the
 * same registry and company (spec 0364): the lower residual is collected and paid
 * with no account, reusing the existing services for the residual of the other
 * side and for the commissions. Documents are locked receivable first, then
 * payable, so two concurrent compensations cannot deadlock.
 */
final class InstallmentCompensationService
{
    public function __construct(
        private readonly InvoiceCollectionService $collections,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data  invoice_installment_id, purchase_invoice_installment_id, compensated_at, note?, residual_mode?, residual_due_date?
     */
    public function register(User $actor, array $data): InstallmentCompensation
    {
        return DB::transaction(function () use ($actor, $data): InstallmentCompensation {
            // Step 1: lock both documents, receivable first
            [$receivable, $payable] = $this->lockPair((int) $data['invoice_installment_id'], (int) $data['purchase_invoice_installment_id']);

            // Step 2: the pair must be compensable
            $this->assertCompensable($receivable, $payable);

            // Step 3: the lower residual closes one side; the other side keeps the rest
            $comparison = bccomp((string) $receivable->amount, (string) $payable->amount, 2);
            $amount = $comparison <= 0 ? (string) $receivable->amount : (string) $payable->amount;
            $remainder = $comparison === 0 ? [] : $this->remainderPayload($data);

            $compensation = InstallmentCompensation::query()->create([
                'company_id' => $receivable->invoice->company_id,
                'registry_id' => $receivable->invoice->customer_registry_id,
                'invoice_installment_id' => $receivable->id,
                'purchase_invoice_installment_id' => $payable->id,
                'amount' => $amount,
                'compensated_at' => $data['compensated_at'],
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Step 4: collect and pay without an account; the larger side takes the residual choice
            $this->collections->record($receivable, [
                'collected_amount' => $amount,
                'collected_at' => $data['compensated_at'],
                ...($comparison > 0 ? $remainder : []),
            ]);
            $this->payments->record($payable, [
                'paid_amount' => $amount,
                'paid_at' => $data['compensated_at'],
                ...($comparison < 0 ? $remainder : []),
            ]);

            // Step 5: tag both installments
            InvoiceInstallment::query()->findOrFail($receivable->id)->update(['compensation_id' => $compensation->id]);
            PurchaseInvoiceInstallment::query()->findOrFail($payable->id)->update(['compensation_id' => $compensation->id]);

            return $compensation;
        });
    }

    public function cancel(InstallmentCompensation $compensation): void
    {
        DB::transaction(function () use ($compensation): void {
            // Step 1: lock both documents, receivable first
            [$receivable, $payable] = $this->lockPair($compensation->invoice_installment_id, $compensation->purchase_invoice_installment_id);

            // Step 2: untag, then restore the previous plans with the existing clearing
            $receivable->update(['compensation_id' => null]);
            $payable->update(['compensation_id' => null]);
            $this->collections->clear($receivable);
            $this->payments->clear($payable);

            // Step 3: drop the compensation
            $compensation->delete();
        });
    }

    /**
     * @return array{0: InvoiceInstallment, 1: PurchaseInvoiceInstallment}
     */
    private function lockPair(int $receivableId, int $payableId): array
    {
        $receivable = InvoiceInstallment::query()->findOrFail($receivableId);
        $payable = PurchaseInvoiceInstallment::query()->findOrFail($payableId);

        Invoice::query()->whereKey($receivable->invoice_id)->lockForUpdate()->firstOrFail();
        PurchaseInvoice::query()->whereKey($payable->purchase_invoice_id)->lockForUpdate()->firstOrFail();

        // Reloaded after the locks: the checks read the state the transaction will write on
        return [
            InvoiceInstallment::query()->with('invoice')->findOrFail($receivableId),
            PurchaseInvoiceInstallment::query()->with('purchaseInvoice')->findOrFail($payableId),
        ];
    }

    private function assertCompensable(InvoiceInstallment $receivable, PurchaseInvoiceInstallment $payable): void
    {
        $invoice = $receivable->invoice;
        $purchase = $payable->purchaseInvoice;

        if ($invoice->type !== InvoiceType::Invoice || $purchase->document_type !== PurchaseInvoiceType::Invoice) {
            $this->refuse('invoice_installment_id', 'proforma');
        }

        if ((int) $invoice->customer_registry_id !== (int) $purchase->supplier_id) {
            $this->refuse('invoice_installment_id', 'different_registry');
        }

        if ((int) $invoice->company_id !== (int) $purchase->company_id) {
            $this->refuse('invoice_installment_id', 'different_company');
        }

        if (! $this->isOpen($receivable->collected_amount, $receivable->write_off_amount, $receivable->compensation_id)) {
            $this->refuse('invoice_installment_id', 'not_open');
        }

        if (! $this->isOpen($payable->paid_amount, $payable->write_off_amount, $payable->compensation_id)) {
            $this->refuse('purchase_invoice_installment_id', 'not_open');
        }
    }

    private function isOpen(?string $settled, ?string $writeOff, ?int $compensationId): bool
    {
        return bccomp($settled ?? '0', '0', 2) === 0 && $writeOff === null && $compensationId === null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function remainderPayload(array $data): array
    {
        $mode = ResidualMode::tryFrom((string) ($data['residual_mode'] ?? ''));

        if ($mode === null || $mode === ResidualMode::WriteOff) {
            $this->refuse('residual_mode', 'residual_mode_required');
        }

        return array_filter(['residual_mode' => $mode->value, 'residual_due_date' => $data['residual_due_date'] ?? null]);
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => [__("treasury_compensations.{$key}")]]);
    }
}
