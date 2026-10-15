<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementStatus;
use App\Enums\PurchaseInvoiceType;
use App\Models\CommissionSettlement;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoiceAmountCalculator;
use App\Services\PurchaseInvoices\PurchaseInvoiceService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Approval of a settlement with the agent document received (spec 0267, D-4):
 * the payable invoice is created through PurchaseInvoiceService::create (same
 * rules and guards as the module) and linked, in the same transaction as the
 * settlement lock. The approval window data comes from draft().
 */
class SettlementApprovalService
{
    /** Errors of the purchase invoice mapped to the keys of the approval contract. */
    private const array ERROR_KEYS = [
        'supplier_id' => 'supplier',
        'withholding_amount' => 'document_withholding_amount',
        'enasarco_amount' => 'document_enasarco_amount',
    ];

    public function __construct(
        private readonly CommissionSettlementService $settlements,
        private readonly AgentSupplierService $suppliers,
        private readonly PurchaseInvoiceService $invoices,
        private readonly PurchaseInvoiceAmountCalculator $amounts,
        private readonly DocumentToleranceChecker $checker,
    ) {}

    /**
     * Data of the approval window: the linked supplier or its candidates and the profile defaults.
     *
     * @return array<string, mixed>
     */
    public function draft(CommissionSettlement $settlement, User $actor): array
    {
        $this->settlements->assertDocumentReceived($settlement);
        $settlement->loadMissing(['referent.supplierRegistry:id,name', 'referent.personalData', 'agentFiscalProfile']);
        $supplier = $settlement->referent->supplierRegistry;
        $profile = $settlement->agentFiscalProfile;

        return [
            'supplier' => $supplier === null ? null : ['id' => $supplier->id, 'name' => $supplier->name],
            'supplier_candidates' => $supplier === null ? $this->suppliers->candidates($settlement->referent) : [],
            'can_create_supplier' => $actor->can('create', Registry::class),
            'payment_method_id' => $profile?->payment_method_id,
            'cost_type_id' => $profile?->cost_type_id,
            'vat_rate_id' => $profile?->vat_rate_id,
            'line_description' => $this->lineDescription($settlement),
        ];
    }

    /**
     * @param  array{payment_method_id: int, cost_type_id: int, vat_rate_id: int, financial_account_id?: int|null}  $data
     */
    public function approve(CommissionSettlement $settlement, array $data, User $actor): CommissionSettlement
    {
        return $this->settlements->transition($settlement, function (CommissionSettlement $locked) use ($data, $actor): void {
            // Step 1: state and supplier
            $this->settlements->assertDocumentReceived($locked);
            $supplier = $this->supplierOf($locked);

            // Step 2: the invoice payload, its VAT within the tolerance of the document
            $payload = $this->invoicePayload($locked, $supplier, $data);
            $this->assertVatWithinTolerance($locked, $payload);

            // Step 3: the invoice, through the module (errors remapped to the contract keys)
            $invoice = $this->createInvoice($actor, $payload);

            // Step 4: approve and link
            $locked->fill([
                'status' => CommissionSettlementStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'purchase_invoice_id' => $invoice->id,
            ])->save();
        });
    }

    private function supplierOf(CommissionSettlement $settlement): Registry
    {
        $supplier = Registry::query()->find($settlement->referent()->value('supplier_registry_id'));

        if ($supplier === null || ! $supplier->is_supplier) {
            throw ValidationException::withMessages(['supplier' => [__('The agent has no supplier registry: link or create one first.')]]);
        }

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function invoicePayload(CommissionSettlement $settlement, Registry $supplier, array $data): array
    {
        return [
            'document_type' => PurchaseInvoiceType::Invoice->value,
            'company_id' => $settlement->company_id,
            'supplier_id' => $supplier->id,
            'document_number' => $settlement->document_number,
            'document_date' => $settlement->document_date->toDateString(),
            'payment_method_id' => $data['payment_method_id'],
            'financial_account_id' => $data['financial_account_id'] ?? null,
            'cost_type_id' => $data['cost_type_id'],
            'withholding_amount' => (string) $settlement->document_withholding_amount,
            'enasarco_amount' => (string) $settlement->document_enasarco_amount,
            'notes' => __('Commission settlement :code', ['code' => $settlement->code()]),
            'lines' => [[
                'description' => $this->lineDescription($settlement),
                'quantity' => 1,
                'unit_price' => (string) $settlement->document_taxable_amount,
                'vat_rate_id' => $data['vat_rate_id'],
                'cost_type_id' => $data['cost_type_id'],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertVatWithinTolerance(CommissionSettlement $settlement, array $payload): void
    {
        $computed = $this->amounts->compute($payload['lines'])['vat'];
        $violation = $this->checker->violation(
            bcsub($computed, (string) $settlement->document_vat_amount, 2),
            (string) config('commissions.document_tolerance'),
        );

        if ($violation !== null) {
            throw ValidationException::withMessages([
                'vat_rate_id' => [__('The amount differs from the settlement by :difference, over the tolerance of :tolerance.', $violation)],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createInvoice(User $actor, array $payload): PurchaseInvoice
    {
        try {
            return $this->invoices->create($actor, $payload);
        } catch (ValidationException $exception) {
            $remapped = [];

            foreach ($exception->errors() as $key => $messages) {
                $remapped[Str::startsWith($key, 'lines') ? 'vat_rate_id' : (self::ERROR_KEYS[$key] ?? $key)] = $messages;
            }

            throw ValidationException::withMessages($remapped);
        }
    }

    private function lineDescription(CommissionSettlement $settlement): string
    {
        return __('Commissions :from-:to', [
            'from' => $settlement->period_from->format('d/m/Y'),
            'to' => $settlement->period_to->format('d/m/Y'),
        ]);
    }
}
