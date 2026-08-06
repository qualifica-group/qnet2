<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Quote;
use App\Services\Contracts\ContractAlertResolver;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 *
 * Shape frozen by spec 0072's data_contract (GET/PATCH /api/contracts/{contract},
 * MT-02). D-1: `contracts` owns NO client/opportunity/amount/code column —
 * every one of those is projected LIVE off the eager-loaded `quote` tree
 * (ContractService::DETAIL_RELATIONS having loaded `quote.opportunity.registry`,
 * `quote.company`, `quote.companySite`, `quote.operationalSite.addresses.city`,
 * `quote.commercial`/`reporter`/`supervisor`/`paymentMethod`,
 * `quote.offerLines.product.category`/`vatRate`/`commissions.recipient`), so
 * resolving any of them here never N+1s (AC-040). `code` shown is always
 * `quote.code`, never a contract-owned column.
 *
 * `offer_lines` (BR-7) is `quote.offerLines`, rendered read-only with the
 * SAME QuoteLineResource the Offerta tab uses — no write endpoint exists on
 * this module for those rows.
 *
 * `alert`/`days_to_expiry`/`days_to_renewal` (BR-6, D-4) are computed by the
 * shared `ContractAlertResolver` — never re-derived here, so this detail read
 * and `ContractsTableDefinition` (MT-04) can never disagree.
 *
 * `summary.*.gross` (like `quote.revenue_gross`) is DERIVED here (net + vat)
 * at request time — NEVER persisted (D-9, same rule as QuoteResource).
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alertResolver = app(ContractAlertResolver::class);

        /** @var Quote $quote */
        $quote = $this->quote;

        return [
            'id' => $this->id,
            'quote_id' => $this->quote_id,
            'quote' => $this->summarizeQuote($quote),
            'registry' => $this->summarizeByName($quote->opportunity?->registry),
            'opportunity' => $this->summarizeByName($quote->opportunity),
            'company' => $this->summarizeCompany($quote->company),
            'company_site' => $this->summarizeByName($quote->companySite),
            'operational_site' => OperationalSiteLabel::summarize($quote->operationalSite),
            'commercial' => $this->summarizeByName($quote->commercial),
            'reporter' => $this->summarizeByName($quote->reporter),
            'supervisor' => $this->summarizeByName($quote->supervisor),
            'payment_method' => $this->summarizeByName($quote->paymentMethod),
            'contract_status_id' => $this->contract_status_id,
            'contract_status' => $this->summarizeStatus($this->contractStatus),
            'accepted_at' => $this->accepted_at,
            'validated_at' => $this->validated_at,
            'renewal_date' => $this->renewal_date,
            'expiry_date' => $this->expiry_date,
            'terminated_at' => $this->terminated_at,
            'termination_reason' => $this->termination_reason,
            'payment_notes' => $this->payment_notes,
            'comments' => $this->comments,
            'validated_by' => $this->summarizeByName($this->validatedBy),
            'terminated_by' => $this->summarizeByName($this->terminatedBy),
            'suspended_at' => $this->suspended_at,
            'status_before_suspension' => $this->summarizeStatus($this->statusBeforeSuspension),
            'is_suspended' => $this->isSuspended(),
            'alert' => $alertResolver->resolve($this->resource),
            'days_to_expiry' => $alertResolver->daysToExpiry($this->resource),
            'days_to_renewal' => $alertResolver->daysToRenewal($this->resource),
            'offer_lines' => QuoteLineResource::collection($quote->offerLines),
            'summary' => $this->summarizeTotals($quote),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The `quote` sub-object (data_contract): the preventivo's own identity
     * and amounts, projected read-only — never the source of truth here,
     * `quotes` remains it (D-1).
     *
     * @return array{id: int, code: string, title: string, created_at: mixed, revenue_net: string, revenue_vat: string, revenue_gross: string, cost_net: string, margin_net: string}
     */
    private function summarizeQuote(Quote $quote): array
    {
        return [
            'id' => $quote->id,
            'code' => $quote->code,
            'title' => $quote->title,
            'created_at' => $quote->created_at,
            'revenue_net' => $quote->revenue_net,
            'revenue_vat' => $quote->revenue_vat,
            'revenue_gross' => $this->grossOf($quote->revenue_net, $quote->revenue_vat),
            'cost_net' => $quote->cost_net,
            'margin_net' => $quote->margin_net,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * `companies` has no `name` column: its display name IS `denomination`
     * (spec 0010, same projection as QuoteResource::summarizeCompany()).
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeCompany(?Company $company): ?array
    {
        return $company === null ? null : ['id' => $company->id, 'name' => $company->denomination];
    }

    /**
     * A `ContractStatus` row (either `contract_status` or
     * `status_before_suspension`), same `{id, name, color, group}` shape
     * QuoteResource uses for `quote_workflow_status`.
     *
     * @return array{id: int, name: string, color: string|null, group: string}|null
     */
    private function summarizeStatus(?Model $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function summarizeTotals(Quote $quote): array
    {
        return [
            'revenue' => $this->amountTriplet($quote->revenue_net, $quote->revenue_vat),
            'cost' => $this->amountTriplet($quote->cost_net, $quote->cost_vat),
            'margin' => ['net' => $quote->margin_net],
        ];
    }

    /**
     * @return array{net: string, vat: string, gross: string}
     */
    private function amountTriplet(string $net, string $vat): array
    {
        return [
            'net' => $net,
            'vat' => $vat,
            'gross' => $this->grossOf($net, $vat),
        ];
    }

    private function grossOf(string $net, string $vat): string
    {
        return number_format((float) $net + (float) $vat, 2, '.', '');
    }
}
