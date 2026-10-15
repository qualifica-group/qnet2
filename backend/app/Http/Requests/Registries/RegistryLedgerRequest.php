<?php

namespace App\Http\Requests\Registries;

use App\DataObjects\Ledger\LedgerFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /registries/{registry}/ledger (spec 0266). Authorization runs before the
 * validation: `ledger.view` plus at least one module the ledger reads from.
 */
class RegistryLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && $actor->can('ledger.view')
            && ($actor->can('invoices.view') || $actor->can('purchase-invoices.view'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['sometimes', Rule::in([LedgerFilters::TYPE_ALL, LedgerFilters::TYPE_PROFORMA, LedgerFilters::TYPE_INVOICE])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'company_id' => ['sometimes', 'array'],
            'company_id.*' => ['integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'array'],
            'company_site_id.*' => ['integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'array'],
            'operational_site_id.*' => ['integer', Rule::exists('operational_sites', 'id')],
        ];
    }

    public function filters(): LedgerFilters
    {
        $data = $this->safe();

        return new LedgerFilters(
            documentType: (string) ($data['document_type'] ?? LedgerFilters::TYPE_INVOICE),
            dateFrom: $this->day($data['date_from'] ?? null),
            dateTo: $this->day($data['date_to'] ?? null),
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
        );
    }

    private function day(?string $date): ?CarbonImmutable
    {
        return $date === null ? null : CarbonImmutable::parse($date)->startOfDay();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function ids(array $ids): array
    {
        return array_values(array_map('intval', $ids));
    }
}
