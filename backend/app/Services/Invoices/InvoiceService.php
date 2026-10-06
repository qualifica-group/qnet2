<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\FinancialAccountType;
use App\Enums\InvoiceType;
use App\Enums\ProformaRequestStatus;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic of the active invoicing documents (spec 0194): issue from a
 * proforma request, full edit, details, delete and installment collections.
 * Amounts, number and schedule are always computed here, never by the client.
 */
class InvoiceService
{
    /** Relations the InvoiceResource reads (lazy loading is forbidden). */
    public const array RESOURCE_RELATIONS = [
        'company:id,denomination',
        'customer:id,name',
        'customer.personalData',
        'paymentMethod:id,name',
        'financialAccount:id,name,iban',
        'proformaRequest:id,kind',
        'workOrder:id,code,title',
        'quote:id,code',
        'lines.product:id,name',
        'lines.vatRate:id,name,rate',
        'installments',
        'createdBy:id,name',
    ];

    /** Optional header keys: written only when the payload carries them. */
    private const array OPTIONAL_HEADER_KEYS = ['financial_account_id', 'notes', 'internal_note', 'tag'];

    public function __construct(
        private readonly InvoiceAmountCalculator $amounts,
        private readonly InvoiceInstallmentPlanner $planner,
        private readonly InvoiceNumberAllocator $numbers,
    ) {}

    /**
     * Issue the document of a proforma request (one per request, D-7).
     *
     * @param  array<string, mixed>  $data  validated InvoiceWritePayload
     */
    public function create(ProformaRequest $request, User $actor, array $data): Invoice
    {
        return DB::transaction(function () use ($request, $actor, $data): Invoice {
            // Step 1: serialize on the request row and refuse a second document
            $locked = ProformaRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $this->assertNotInvoiced($locked);

            // Step 2: validate and compute everything before touching the sequence
            $workOrder = WorkOrder::query()->findOrFail($locked->work_order_id, ['id', 'quote_id']);
            $prepared = $this->prepare($data, $workOrder->id);

            // Step 3: allocate the number and persist header, lines, schedule
            $invoice = Invoice::query()->create($prepared['header'] + [
                'type' => InvoiceType::Proforma,
                'number' => $this->numbers->next($prepared['header']['company_id'], CarbonImmutable::parse($data['document_date'])->year),
                'year' => CarbonImmutable::parse($data['document_date'])->year,
                'proforma_request_id' => $locked->id,
                'work_order_id' => $workOrder->id,
                'quote_id' => $workOrder->quote_id,
                'created_by' => $actor->id,
            ]);
            $this->writeChildren($invoice, $prepared);

            // Step 4: mark the request as issued
            $locked->update(['status' => ProformaRequestStatus::Issued, 'issued_at' => now()]);

            return $this->detail($invoice);
        });
    }

    /**
     * Full edit: lines replaced and schedule recomputed; number and company are fixed.
     *
     * @param  array<string, mixed>  $data  validated InvoiceWritePayload
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            // Step 1: lock the row and refuse an edit once money was collected
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $this->assertNoCollections($locked, 'This invoice has collected installments and cannot be modified.');

            if ((int) $data['company_id'] !== $locked->company_id) {
                throw ValidationException::withMessages(['company_id' => ['The issuing company cannot be changed.']]);
            }

            // Step 2: validate and compute, then replace header, lines and schedule
            $prepared = $this->prepare($data, $locked->work_order_id);
            $locked->update($prepared['header']);
            $this->writeChildren($locked, $prepared);

            return $this->detail($locked);
        });
    }

    /**
     * External number/date, tag, deviation and internal note: always allowed.
     * The external number decides the type (proforma becomes invoice, D-3/D-14).
     *
     * @param  array<string, mixed>  $data  validated details payload
     */
    public function updateDetails(Invoice $invoice, array $data): Invoice
    {
        $attributes = Arr::only($data, ['external_number', 'external_date', 'tag', 'deviation', 'internal_note']);

        if (array_key_exists('external_number', $attributes)) {
            $registered = filled($attributes['external_number']);
            $attributes['type'] = $registered ? InvoiceType::Invoice : InvoiceType::Proforma;

            if (! $registered) {
                $attributes['external_number'] = $attributes['external_date'] = null;
            }
        }

        $invoice->update($attributes);

        return $this->detail($invoice);
    }

    /** Delete the document and give its request back to Accounting (D-7); the number is not reused. */
    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $this->assertNoCollections($locked, 'This invoice has collected installments and cannot be deleted.');

            ProformaRequest::query()->whereKey($locked->proforma_request_id)
                ->update(['status' => ProformaRequestStatus::Pending, 'issued_at' => null]);

            $locked->delete();
        });
    }

    public function recordCollection(InvoiceInstallment $installment, string $amount, string $collectedAt): Invoice
    {
        $installment->update(['collected_amount' => $this->amounts->normalize($amount), 'collected_at' => $collectedAt]);

        return $this->detail($installment->invoice);
    }

    public function clearCollection(InvoiceInstallment $installment): Invoice
    {
        $installment->update(['collected_amount' => null, 'collected_at' => null]);

        return $this->detail($installment->invoice);
    }

    /**
     * Schedule shown by the modal before saving.
     *
     * @param  array<string, mixed>  $data  validated preview payload
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null}>
     */
    public function previewInstallments(array $data): array
    {
        $schedule = $this->planner->plan(
            CarbonImmutable::parse($data['document_date']),
            PaymentMethod::query()->findOrFail($data['payment_method_id']),
            $this->amounts->normalize($data['net_amount']),
            $this->amounts->normalize($data['vat_amount']),
            $this->amounts->normalize($data['total_amount']),
        );

        return array_map(fn (array $row): array => ['due_date' => $row['due_date']->toDateString()] + $row, $schedule);
    }

    public function detail(Invoice $invoice): Invoice
    {
        return $invoice->fresh(self::RESOURCE_RELATIONS);
    }

    /**
     * Validate the business rules and compute header, lines and schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>, installments: array<int, array<string, mixed>>}
     */
    private function prepare(array $data, ?int $workOrderId): array
    {
        $this->assertBankBelongsToCompany($data);
        $this->assertLinesBelongToWorkOrder($data['lines'], $workOrderId);

        $computed = $this->amounts->compute($data['lines'], $this->amounts->vatRates(array_column($data['lines'], 'vat_rate_id')));

        if (bccomp($computed['total'], '0', 2) <= 0) {
            throw ValidationException::withMessages(['lines' => ['The document total must be greater than zero.']]);
        }

        $installments = $this->planner->plan(
            CarbonImmutable::parse($data['document_date']),
            PaymentMethod::query()->findOrFail($data['payment_method_id']),
            $computed['net'],
            $computed['vat'],
            $computed['total'],
        );

        $header = Arr::only($data, ['document_date', 'company_id', 'customer_registry_id', 'payment_method_id', ...self::OPTIONAL_HEADER_KEYS])
            + ['net_amount' => $computed['net'], 'vat_amount' => $computed['vat'], 'total_amount' => $computed['total']];

        return ['header' => $header, 'lines' => $computed['lines'], 'installments' => $installments];
    }

    /**
     * @param  array<string, mixed>  $prepared
     */
    private function writeChildren(Invoice $invoice, array $prepared): void
    {
        $invoice->lines()->delete();
        $invoice->installments()->delete();
        $invoice->lines()->createMany($prepared['lines']);
        $invoice->installments()->createMany($prepared['installments']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertBankBelongsToCompany(array $data): void
    {
        if (empty($data['financial_account_id'])) {
            return;
        }

        $valid = FinancialAccount::query()
            ->whereKey($data['financial_account_id'])
            ->where('type', FinancialAccountType::BankAccount->value)
            ->where('company_id', $data['company_id'])
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['financial_account_id' => ['The bank account must belong to the issuing company.']]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLinesBelongToWorkOrder(array $lines, ?int $workOrderId): void
    {
        $allowed = $workOrderId === null
            ? []
            : WorkOrder::query()->findOrFail($workOrderId, ['id'])->quoteLines()->pluck('quote_lines.id')->all();
        $errors = [];

        foreach (array_values($lines) as $index => $line) {
            if (! empty($line['quote_line_id']) && ! in_array((int) $line['quote_line_id'], $allowed, true)) {
                $errors["lines.{$index}.quote_line_id"] = ['The line does not belong to the work order of this request.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function assertNotInvoiced(ProformaRequest $request): void
    {
        if ($request->status === ProformaRequestStatus::Issued || $request->invoice()->exists()) {
            abort(409, 'This proforma request has already been invoiced.');
        }
    }

    private function assertNoCollections(Invoice $invoice, string $message): void
    {
        if ($invoice->installments()->where('collected_amount', '>', 0)->exists()) {
            abort(409, $message);
        }
    }
}
