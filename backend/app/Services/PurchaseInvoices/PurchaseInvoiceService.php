<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Enums\PurchaseInvoiceType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderInvoiceReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic of the payable documents (spec 0250): create, full edit,
 * supplier details, delete and the schedule preview. Amounts and schedule are
 * always computed here, never by the client; the document row is locked for
 * every write. A registered invoice (type=invoice) is frozen but for its
 * details (RegisteredPurchaseInvoiceGuard).
 */
class PurchaseInvoiceService
{
    /** Relations the PurchaseInvoiceResource reads (lazy loading is forbidden). */
    public const array RESOURCE_RELATIONS = [
        'company:id,denomination',
        'supplier:id,name',
        'paymentMethod:id,name',
        'financialAccount:id,name,iban',
        'companySite:id,name',
        'operationalSite.addresses.city',
        'costType:id,name,color',
        'workOrder:id,code,title',
        'purchaseOrders:id,number,year',
        'lines.purchaseOrderLine.purchaseOrder:id,number,year',
        'lines.product:id,name',
        'lines.vatRate:id,name,rate',
        'lines.costType:id,name,color',
        'lines.workOrder:id,code,title',
        'installments.financialAccount:id,name',
        'createdBy:id,name',
        'commissionSettlement:id,purchase_invoice_id,number,year',
    ];

    /** Header keys written only when the payload carries them. */
    private const array OPTIONAL_HEADER_KEYS = ['financial_account_id', 'company_site_id', 'operational_site_id', 'cost_type_id', 'work_order_id', 'notes'];

    public function __construct(
        private readonly PurchaseInvoiceAmountCalculator $amounts,
        private readonly PurchaseInvoiceInstallmentPlanner $planner,
        private readonly PurchaseInvoiceScheduleRebalancer $schedule,
        private readonly PurchaseInvoiceLineGuard $lineGuard,
        private readonly PurchaseOrderInvoiceReceiptService $invoiceReceipts,
        private readonly RegisteredPurchaseInvoiceGuard $registeredGuard,
        private readonly PurchaseInvoiceHeaderGuard $headerGuard,
        private readonly LinkedSettlementGuard $settlementGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated write payload (POST)
     */
    public function create(User $actor, array $data): PurchaseInvoice
    {
        return $this->guardingDuplicates(fn (): PurchaseInvoice => DB::transaction(function () use ($actor, $data): PurchaseInvoice {
            // Step 1: the registration rules and the computed document
            $type = PurchaseInvoiceType::from($data['document_type']);
            $number = $this->normalizeNumber($data['document_number'] ?? null);

            if ($type === PurchaseInvoiceType::Invoice) {
                $this->registeredGuard->assertNumberUnique((int) $data['supplier_id'], $number, CarbonImmutable::parse($data['document_date'])->year);
            }

            $prepared = $this->prepare($data, null);

            // Step 2: persist header, lines, schedule and order links
            $document = PurchaseInvoice::query()->create($prepared['header'] + [
                'document_type' => $type,
                'document_number' => $number,
                'supplier_id' => $data['supplier_id'],
                'company_id' => $data['company_id'],
                'created_by' => $actor->id,
            ]);
            $this->writeChildren($document, $prepared);

            // Step 3: an invoice receives what it bills and is not received yet (spec 0254, D-12)
            if ($type === PurchaseInvoiceType::Invoice) {
                $this->invoiceReceipts->receive($document, $actor, true);
            }

            return $this->detail($document);
        }));
    }

    /**
     * Full edit of a proforma: lines replaced and schedule recomputed; supplier
     * and company are fixed. With payments the schedule is rebalanced instead.
     *
     * @param  array<string, mixed>  $data  validated write payload (PUT)
     */
    public function update(PurchaseInvoice $document, array $data): PurchaseInvoice
    {
        return $this->guardingDuplicates(fn (): PurchaseInvoice => DB::transaction(function () use ($document, $data): PurchaseInvoice {
            // Step 1: lock the row, refuse a registered invoice and a change of the parties
            $locked = PurchaseInvoice::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $this->settlementGuard->assertNotLinked($locked);
            $this->registeredGuard->assertEditable($locked);
            $this->headerGuard->assertPartiesUnchanged($locked, $data);

            // Step 2: validate and compute; with payments plan the rebalancing first
            $paid = $this->schedule->hasPayments($locked);
            $paid && $this->schedule->assertFrozenFields($locked, $data);
            $prepared = $this->prepare($data, $locked->id, ! $paid);
            $plan = $paid ? $this->schedule->plan($locked, $prepared['payable']) : null;

            // Step 3: replace header, lines and schedule
            $locked->update($prepared['header']);

            if ($plan === null) {
                $this->writeChildren($locked, $prepared);
            } else {
                $this->writeLines($locked, $prepared);
                $this->schedule->apply($locked, $plan);
            }

            return $this->detail($locked);
        }));
    }

    /**
     * Supplier number and notes. The number decides the type (a proforma
     * becomes invoice, a cleared number makes it a proforma again); changing it
     * on a registered invoice needs the downgrade permission.
     *
     * @param  array<string, mixed>  $data  validated details payload
     */
    public function updateDetails(PurchaseInvoice $document, array $data, User $actor): PurchaseInvoice
    {
        $attributes = Arr::only($data, ['notes']);

        if (array_key_exists('document_number', $data)) {
            $number = $this->normalizeNumber($data['document_number']);
            $attributes['document_number'] = $number;
            $attributes['document_type'] = $number === null ? PurchaseInvoiceType::Proforma : PurchaseInvoiceType::Invoice;
        }

        $this->guardingDuplicates(fn () => DB::transaction(function () use ($document, $attributes, $actor): void {
            $locked = PurchaseInvoice::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $converting = false;

            if (array_key_exists('document_number', $attributes)) {
                if ($attributes['document_number'] !== $locked->document_number) {
                    $this->settlementGuard->assertNotLinked($locked);
                }

                $this->registeredGuard->assertNumberChangeAllowed($locked, $attributes['document_number'], $actor);

                if ($attributes['document_type'] === PurchaseInvoiceType::Invoice) {
                    $this->registeredGuard->assertNumberUnique($locked->supplier_id, $attributes['document_number'], $locked->document_date->year, $locked->id);
                }

                $converting = $attributes['document_type'] === PurchaseInvoiceType::Invoice && ! $locked->isRegistered();
            }

            $locked->update($attributes);

            // A proforma becoming an invoice receives what it bills and is not received yet (spec 0254, D-12)
            if ($converting) {
                $this->invoiceReceipts->receive($locked, $actor, false);
            }
        }));

        return $this->detail($document);
    }

    /** Delete the document with lines, schedule, order links and attachments. */
    public function delete(PurchaseInvoice $document): void
    {
        DB::transaction(function () use ($document): void {
            $locked = PurchaseInvoice::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $this->settlementGuard->assertNotLinked($locked);
            $this->registeredGuard->assertEditable($locked);

            if ($this->schedule->hasPayments($locked)) {
                abort(409, __('purchase_invoices.has_payments'));
            }

            $locked->delete();
        });
    }

    /**
     * Schedule shown by the editor before saving; the rebalanced plan when the
     * edited document already has payments.
     *
     * @param  array<string, mixed>  $data  validated preview payload
     * @return array<int, array{sequence: int, due_date: string, amount: string, payment_method_code: string|null, paid_amount: string|null, locked: bool}>
     */
    public function previewInstallments(array $data): array
    {
        $document = isset($data['purchase_invoice_id']) ? PurchaseInvoice::query()->find($data['purchase_invoice_id']) : null;

        if ($document !== null && $this->schedule->hasPayments($document)) {
            return $this->schedule->preview($document, $this->payableOf($this->money($data['total_amount']), $data));
        }

        return $this->schedule->previewRows($this->planner->plan(
            CarbonImmutable::parse($data['document_date']),
            PaymentMethod::query()->findOrFail($data['payment_method_id']),
            bcsub($this->money($data['net_amount']), $this->withheld($data), 2),
            $this->money($data['vat_amount']),
            $this->payableOf($this->money($data['total_amount']), $data),
        ));
    }

    public function detail(PurchaseInvoice $document): PurchaseInvoice
    {
        return $document->fresh(self::RESOURCE_RELATIONS);
    }

    /**
     * Validate the business rules and compute header, lines and schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>, installments: array<int, array<string, mixed>>, order_ids: array<int, int>, payable: string}
     */
    private function prepare(array $data, ?int $ignoreDocumentId, bool $withSchedule = true): array
    {
        $this->headerGuard->assertBankBelongsToCompany($data);
        $orderIds = $this->lineGuard->assert($data, $ignoreDocumentId);
        $computed = $this->amounts->compute($data['lines']);

        if (bccomp($computed['total'], '0', 2) <= 0) {
            throw ValidationException::withMessages(['lines' => [__('purchase_invoices.total_not_positive')]]);
        }

        // The withholding and ENASARCO reduce what is paid: the schedule sums to the payable amount (spec 0267, D-6)
        $payable = $this->payableOf($computed['total'], $data);

        if (bccomp($payable, '0', 2) <= 0) {
            throw ValidationException::withMessages(['withholding_amount' => [__('purchase_invoices.payable_not_positive')]]);
        }

        $installments = $withSchedule ? $this->planner->plan(
            CarbonImmutable::parse($data['document_date']),
            PaymentMethod::query()->findOrFail($data['payment_method_id']),
            bcsub($computed['net'], $this->withheld($data), 2),
            $computed['vat'],
            $payable,
        ) : [];

        $header = Arr::only($data, ['document_date', 'payment_method_id', ...self::OPTIONAL_HEADER_KEYS])
            + [
                'net_amount' => $computed['net'], 'vat_amount' => $computed['vat'], 'total_amount' => $computed['total'],
                'withholding_amount' => $this->money($data['withholding_amount'] ?? 0),
                'enasarco_amount' => $this->money($data['enasarco_amount'] ?? 0),
            ];

        return ['header' => $header, 'lines' => $computed['lines'], 'installments' => $installments, 'order_ids' => $orderIds, 'payable' => $payable];
    }

    /**
     * @param  array<string, mixed>  $data  carries the optional withholding and ENASARCO amounts
     */
    private function withheld(array $data): string
    {
        return bcadd($this->money($data['withholding_amount'] ?? 0), $this->money($data['enasarco_amount'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payableOf(string $total, array $data): string
    {
        return bcsub($total, $this->withheld($data), 2);
    }

    /**
     * @param  array<string, mixed>  $prepared
     */
    private function writeChildren(PurchaseInvoice $document, array $prepared): void
    {
        $this->writeLines($document, $prepared);
        $document->installments()->delete();
        $document->installments()->createMany($prepared['installments']);
    }

    /**
     * @param  array<string, mixed>  $prepared
     */
    private function writeLines(PurchaseInvoice $document, array $prepared): void
    {
        $document->lines()->delete();
        $document->lines()->createMany($prepared['lines']);
        $document->purchaseOrders()->sync($prepared['order_ids']);
    }

    /** The unique index (supplier number per year) backs the application check under concurrency. */
    private function guardingDuplicates(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['document_number' => [__('purchase_invoices.duplicate_number')]]);
        }
    }

    private function normalizeNumber(mixed $number): ?string
    {
        $text = is_string($number) ? trim($number) : '';

        return $text === '' ? null : $text;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
