<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\FinancialAccountType;
use App\Enums\ProformaRequestKind;
use App\Models\FinancialAccount;
use App\Models\ProformaRequest;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Support\Amounts\LineAmountCalculator;
use DateTimeImmutable;

/**
 * Read model of the issue modal (spec 0194, D-8/D-9): the lines the work order
 * offers for this request, the defaults and the bank accounts to pick from.
 */
final class InvoiceDraftBuilder
{
    /** Same quantity precision as the invoice lines (InvoiceAmountCalculator). */
    private const int QUANTITY_SCALE = 2;

    public function __construct(
        private readonly ProformaRequestBillableLines $billableLines,
        private readonly SupplierCommissionCalculator $commissions,
        private readonly LineAmountCalculator $amounts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ProformaRequest $request): array
    {
        // Step 1: load the request context
        $request->load('workOrder', 'quote.company', 'quote.paymentMethod', 'quote.opportunity.registry', 'supplier', 'paymentMethod');
        $workOrder = $request->workOrder;
        $quote = $request->quote;

        // Step 2: bank accounts of every company, each tagged with its company
        $banks = FinancialAccount::query()
            ->where('type', FinancialAccountType::BankAccount->value)
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'iban', 'company_id']);

        // Step 3: defaults, then the whole payload; commissions resolve at today's rules (spec 0288, D-4)
        $referenceDate = new DateTimeImmutable('today');
        $company = $quote->company;
        $companyBanks = $banks->where('company_id', $company?->id);
        $paymentMethod = $request->paymentMethod ?? $quote->paymentMethod;

        return [
            'proforma_request' => ['id' => $request->id, 'kind' => $request->kind, 'note' => $request->note],
            'work_order' => ['id' => $workOrder->id, 'code' => $workOrder->code, 'title' => $workOrder->title],
            'quote' => ['id' => $quote->id, 'code' => $quote->code],
            'defaults' => [
                'document_date' => now()->toDateString(),
                'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->denomination],
                'customer' => $this->ref($this->defaultCustomer($request)),
                'payment_method' => $paymentMethod === null ? null : ['id' => $paymentMethod->id, 'name' => $paymentMethod->name],
                'financial_account' => $companyBanks->count() === 1 ? $this->bank($companyBanks->first()) : null,
                'notes' => null,
                'layout' => null,
            ],
            'available_lines' => $this->billableLines->available($request)
                ->map(fn (QuoteLine $line): array => $this->line($line, $request->kind, $referenceDate))->values()->all(),
            'bank_accounts' => $banks->map(fn (FinancialAccount $bank): array => $this->bank($bank) + ['company_id' => $bank->company_id])->values()->all(),
        ];
    }

    private function defaultCustomer(ProformaRequest $request): ?Registry
    {
        $opportunityRegistry = $request->quote->opportunity?->registry;

        return $request->kind === ProformaRequestKind::Institution
            ? ($request->supplier ?? $opportunityRegistry)
            : $opportunityRegistry;
    }

    /**
     * Consultancy lines bill the sale amounts; institution lines bill the
     * supplier commission on them, as one unit (spec 0288, D-4).
     *
     * @return array<string, mixed>
     */
    private function line(QuoteLine $line, ProformaRequestKind $kind, DateTimeImmutable $referenceDate): array
    {
        // A quote line may carry no VAT rate: fall back to the product's default.
        $vatRate = $line->vatRate ?? $line->product->vatRate;
        $commission = $kind === ProformaRequestKind::Institution ? $this->commissions->forLine($line, $referenceDate) : null;
        $amounts = $commission === null
            ? ['quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'net' => $line->net_amount, 'vat' => $line->vat_amount, 'total' => $line->total_amount]
            : $this->amounts->line('1', $commission->amount, $vatRate?->rate === null ? null : (string) $vatRate->rate, self::QUANTITY_SCALE);

        return [
            'quote_line_id' => $line->id,
            'product' => ['id' => $line->product->id, 'name' => $line->product->name],
            'description' => $line->additional_description ?? $line->product->name,
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate' => $vatRate === null ? null : ['id' => $vatRate->id, 'name' => $vatRate->name, 'rate' => $vatRate->rate],
            'net_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
            'commission' => $commission === null ? null : [
                'type' => $commission->type,
                'value' => $commission->value,
                'base_amount' => $commission->baseAmount,
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, iban: string|null}
     */
    private function bank(FinancialAccount $bank): array
    {
        return ['id' => $bank->id, 'name' => $bank->name, 'iban' => $bank->iban];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function ref(?Registry $registry): ?array
    {
        return $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name];
    }
}
