<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\FinancialAccountType;
use App\Enums\ProformaRequestKind;
use App\Enums\QuoteLineType;
use App\Models\FinancialAccount;
use App\Models\ProformaRequest;
use App\Models\QuoteLine;
use App\Models\Registry;
use Illuminate\Support\Collection;

/**
 * Read model of the issue modal (spec 0194, D-8/D-9): the lines the work order
 * offers for this request, the defaults and the bank accounts to pick from.
 */
final class InvoiceDraftBuilder
{
    /** Product typology codes are immutable: never resolve by name. */
    private const array KIND_TYPOLOGY = [
        'consultancy' => 'consultancy',
        'institution' => 'institution',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(ProformaRequest $request): array
    {
        // Step 1: load the request context
        $request->load('workOrder.quote.company', 'workOrder.quote.paymentMethod', 'workOrder.quote.opportunity.registry', 'supplier', 'paymentMethod');
        $workOrder = $request->workOrder;
        $quote = $workOrder->quote;

        // Step 2: bank accounts of every company, each tagged with its company
        $banks = FinancialAccount::query()
            ->where('type', FinancialAccountType::BankAccount->value)
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'iban', 'company_id']);

        // Step 3: defaults, then the whole payload
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
            ],
            'available_lines' => $this->availableLines($request)->map(fn (QuoteLine $line): array => $this->line($line))->values()->all(),
            'bank_accounts' => $banks->map(fn (FinancialAccount $bank): array => $this->bank($bank) + ['company_id' => $bank->company_id])->values()->all(),
        ];
    }

    private function defaultCustomer(ProformaRequest $request): ?Registry
    {
        $opportunityRegistry = $request->workOrder->quote->opportunity?->registry;

        return $request->kind === ProformaRequestKind::Institution
            ? ($request->supplier ?? $opportunityRegistry)
            : $opportunityRegistry;
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function availableLines(ProformaRequest $request): Collection
    {
        $code = self::KIND_TYPOLOGY[$request->kind->value];

        return $request->workOrder->quoteLines()
            ->where('line_type', QuoteLineType::Revenue->value)
            ->with('product:id,name,supplier_id,product_typology_id,vat_rate_id', 'product.productTypology:id,code', 'product.vatRate:id,name,rate', 'vatRate:id,name,rate')
            ->get()
            ->filter(fn (QuoteLine $line): bool => $line->product?->productTypology?->code === $code)
            ->filter(fn (QuoteLine $line): bool => $request->kind === ProformaRequestKind::Consultancy
                || $line->product->supplier_id === $request->supplier_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(QuoteLine $line): array
    {
        // A quote line may carry no VAT rate: fall back to the product's default.
        $vatRate = $line->vatRate ?? $line->product->vatRate;

        return [
            'quote_line_id' => $line->id,
            'product' => ['id' => $line->product->id, 'name' => $line->product->name],
            'description' => $line->additional_description ?? $line->product->name,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'vat_rate' => $vatRate === null ? null : ['id' => $vatRate->id, 'name' => $vatRate->name, 'rate' => $vatRate->rate],
            'net_amount' => $line->net_amount,
            'vat_amount' => $line->vat_amount,
            'total_amount' => $line->total_amount,
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
