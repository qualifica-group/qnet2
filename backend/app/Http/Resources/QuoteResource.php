<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FormMode;
use App\Http\Resources\Concerns\SummarizesRewards;
use App\Models\Company;
use App\Models\Quote;
use App\Quotes\QuoteAttributeResolver;
use App\RequestManagement\ApplicableAttribute;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use App\Services\Commissions\QuoteCommissionSummaryCalculator;
use App\Services\Quotes\QuoteManagerLabelResolver;
use App\Services\Quotes\QuoteManagerSyncMode;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 *
 * Shape frozen by spec 0065's data_contract (GET/POST/PATCH /api/quotes...,
 * MT-05), amended by spec 0083 (T-04) for the working status. Relies on
 * QuoteService::loadDetail()/DETAIL_RELATIONS having eager-loaded
 * `opportunity`, `quoteWorkflowStatus`, `commercial`, `reporter`,
 * `supervisor`, `offerLines.product.category`, `offerLines.vatRate`,
 * `costLines.product.category`, `costLines.vatRate`, so resolving any of them
 * here never N+1s (per-line product/category/business-function resolution is
 * QuoteLineResource's own concern — see its docblock).
 *
 * `company`/`company_site`/`operational_site` (user directive 2026-07-30)
 * follow the same eager-loaded-then-projected rule: the first two as the
 * standard `{id, name}` ref (a Company's name being its `denomination`), the
 * third as `{id, label}` via OperationalSiteLabel — the site has no own name
 * column, exactly as on ProjectResource/OpportunityResource.
 *
 * `registry`/`referent`/`source`/`product_lines`/`general_notes` (user
 * directive 2026-08-31) are a READ-ONLY projection of the parent Opportunity:
 * `quotes` carries no such column and none of them is writable from here — the
 * offer detail only displays them as the context of the record it belongs to,
 * so they are additive output, not fields of QuotesAuthorization.
 * `product_lines` reuses OpportunityResource's own row shape verbatim, so the
 * shared read-only list renders either record identically. Relies on
 * QuoteService::DETAIL_RELATIONS eager-loading `opportunity.registry`,
 * `opportunity.referent`, `opportunity.source` and
 * `opportunity.productLines.{businessFunction,productCategory}`.
 *
 * `layout`/`layout_id` (spec 0070) and `payment_method`/`payment_method_id`
 * (user directive 2026-07-30) follow the standard `{id, name}` ref shape
 * (`summarizeByName`), additive alongside every pre-existing key.
 *
 * Spec 0083, D-1/D-8: the former flat quote-status pick is REMOVED and
 * replaced by `quote_workflow_status_id`/`quote_workflow_status` (the
 * currently resolved working-state row) and `quote_workflow_statuses` (the
 * full ordered set QuoteWorkflowResolver resolves for THIS offer right now,
 * for the FE's status select). Resolving the set re-runs the resolver (a
 * bounded, controlled query), relying on `offerLines.product.category`/
 * `opportunity.customFieldValueRow` already being eager-loaded so it never
 * N+1s beyond that one query.
 *
 * `summary.*.gross` is DERIVED here (net + vat) at request time — NEVER
 * persisted (D-9): the 5 persisted aggregates (`revenue_net`, `revenue_vat`,
 * `cost_net`, `cost_vat`, `margin_net`) are the only source of truth this
 * resource reads from.
 *
 * Spec 0084 (D-1/D-5): `attribute_values` is the raw quote-level values map
 * (`{}` when null); `applicable_attributes` is the union/dedup-by-code set of
 * THIS quote's own offer lines' effective category attributes
 * (App\Quotes\QuoteAttributeResolver, context `quote`); `attribute_layout`
 * completes the trio with the merged, multi-category layout (spec 0062),
 * `FormMode::Edit` since this resource IS what the edit form hydrates from —
 * `null` when no contributing category configures one (flat rendering).
 * Relies on QuoteService::DETAIL_RELATIONS already eager-loading
 * `offerLines.product.category`, so resolving all three never N+1s.
 *
 * `rewards` (spec 0059 D-3, extended to the Offerta origin by the 2026-08-31
 * directive): the offer's own "abbinamento buono" chips, beneficiary
 * `quotes.reporter_id` — the SAME block, same shape, OpportunityResource
 * emits (SummarizesRewards), so the shared `ReporterRewardsField` hydrates
 * identically from either record.
 *
 * Spec 0087: `managers`/`operator_id` are ADDITIVE — `managers` mirrors
 * OpportunityResource's own `{id, name, position}` shape (ordered by
 * position); `manager_labels` is the QuoteManagerLabelResolver counterpart
 * of OpportunityResource's own field (D-8), `{}` when not resolvable;
 * `operator_id` is the persisted projection of the GA2 "Operatore" slot
 * (D-3), read directly off the column — no resolver needed.
 * `managers_synchronized` (D-7) feeds the FE's informational banner: true
 * when a write on either side of this Offerta's Opportunita' replaces the
 * other's GA list wholesale. Relies on QuoteService::DETAIL_RELATIONS having
 * eager-loaded `managers`, `offerLines.product.category` and (D-8's
 * fallback) `opportunity.productLines.productCategory`, so none of this
 * N+1s.
 *
 * #[PreserveKeys]: `manager_labels` is a sparse position("1".."n")->label
 * map — JsonResource's default filter() reindexes any NESTED array whose
 * keys are ALL numeric, which would silently turn `{"2":"Operatore"}` into
 * `["Operatore"]` on the wire (same reasoning as OpportunityResource's own
 * attribute). Every other array field here is already 0-indexed-sequential,
 * so this is a no-op for them.
 */
#[PreserveKeys]
class QuoteResource extends JsonResource
{
    use SummarizesRewards;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $commissionPermissions = app(QuoteCommissionPayloadRedactor::class)
            ->permissions($request->user(), $this->resource);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'opportunity_id' => $this->opportunity_id,
            'opportunity' => $this->summarizeByName($this->opportunity),
            // Proiezione READ-ONLY del record padre (richiesta utente
            // 2026-08-31): l'anagrafica e il suo referente non hanno colonna
            // propria su `quotes` e non sono scrivibili da qui — il dettaglio
            // Offerta le mostra soltanto, come fa la scheda Opportunita'.
            'registry' => $this->summarizeByName($this->opportunity?->registry),
            'referent' => $this->summarizeByName($this->opportunity?->referent),
            'source' => $this->summarizeByName($this->opportunity?->source),
            'product_lines' => $this->summarizeProductLines($this->opportunity?->productLines ?? []),
            'general_notes' => $this->opportunity?->general_notes,
            'quote_workflow_status_id' => $this->quote_workflow_status_id,
            'quote_workflow_status' => $this->summarizeWorkflowStatus($this->quoteWorkflowStatus),
            'quote_workflow_statuses' => $this->resolveWorkflowStatuses(),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->summarizeByName($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->summarizeByName($this->reporter),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->summarizeByName($this->supervisor),
            'managers' => $this->summarizeManagers($this->managers),
            'manager_labels' => app(QuoteManagerLabelResolver::class)->resolve($this->resource),
            'operator_id' => $this->operator_id,
            'managers_synchronized' => app(QuoteManagerSyncMode::class)->isSynchronized($this->opportunity),
            'company_id' => $this->company_id,
            'company' => $this->summarizeCompany($this->company),
            'company_site_id' => $this->company_site_id,
            'company_site' => $this->summarizeByName($this->companySite),
            'operational_site_id' => $this->operational_site_id,
            'operational_site' => OperationalSiteLabel::summarize($this->operationalSite),
            'layout_id' => $this->layout_id,
            'layout' => $this->summarizeByName($this->layout),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->summarizeByName($this->paymentMethod),
            'internal_notes' => $this->internal_notes,
            'rewards' => $this->summarizeRewards($this->rewards),
            'offer_lines' => QuoteLineResource::collection($this->offerLines),
            'cost_lines' => QuoteLineResource::collection($this->costLines),
            // Cast to object, non array: un array PHP vuoto serializza come `[]`,
            // e il form legge la chiave come una MAPPA (Zod `z.object`) — con `[]`
            // la validazione fallisce su un campo che nessun input rende e il
            // salvataggio si interrompe in silenzio.
            'attribute_values' => (object) ($this->attribute_values ?? []),
            'applicable_attributes' => $this->resolveApplicableAttributes(),
            'attribute_layout' => app(QuoteAttributeResolver::class)->layout($this->resource, FormMode::Edit),
            'summary' => $this->summarizeTotals(
                $commissionPermissions['commissions']->visible
                    && $commissionPermissions['commission_value']->visible,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
     * (spec 0010), projected under `name` so the client keeps the single
     * `{id, name}` relation-ref shape every other relation here uses — the
     * same mapping CompanyForSelectResource applies to `label`.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeCompany(?Company $company): ?array
    {
        return $company === null ? null : ['id' => $company->id, 'name' => $company->denomination];
    }

    /**
     * The parent Opportunity's funzione-aziendale + categoria-prodotto rows,
     * in OpportunityResource's own shape (spec 0040 amendment rev.3).
     *
     * @return array<int, array{id: int, business_function: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        return collect($lines)
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'business_function' => $this->summarizeByName($line->businessFunction),
                'product_category' => $this->summarizeByName($line->productCategory),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, position: int}>
     */
    private function summarizeManagers(iterable $managers): array
    {
        return collect($managers)
            ->map(fn (Model $manager): array => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => (int) $manager->pivot->position,
            ])
            ->all();
    }

    /**
     * The currently resolved working-state row (spec 0083, D-1/D-8).
     *
     * @return array{id: int, name: string, color: string|null, group: string, requires_note: bool}|null
     */
    private function summarizeWorkflowStatus(?Model $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
            'requires_note' => $status->requires_note,
        ];
    }

    /**
     * The full ordered set QuoteWorkflowResolver resolves for this offer
     * RIGHT NOW (spec 0083) — feeds the FE's status select, limited to that
     * set (AC-050). Carries `sort_order` (data contract), unlike the single
     * `quote_workflow_status` above.
     *
     * @return array<int, array{id: int, name: string, color: string|null, group: string, requires_note: bool, sort_order: int}>
     */
    private function resolveWorkflowStatuses(): array
    {
        $resolver = app(QuoteWorkflowResolver::class);

        return $resolver->statusesFor($resolver->resolve($this->resource))
            ->map(fn (Model $status): array => [
                ...$this->summarizeWorkflowStatus($status),
                'sort_order' => $status->sort_order,
            ])
            ->all();
    }

    /**
     * D-5: revenue/cost side by side, margin computed on the net (revenue
     * net minus cost net, persisted as `margin_net` — may be negative,
     * AC-043, never clamped).
     *
     * @return array<string, array<string, string>>
     */
    private function summarizeTotals(bool $mayViewCommissions): array
    {
        $summary = [
            'revenue' => $this->amountTriplet($this->revenue_net, $this->revenue_vat),
            'cost' => $this->amountTriplet($this->cost_net, $this->cost_vat),
            'margin' => ['net' => $this->margin_net],
        ];

        if ($mayViewCommissions) {
            $summary['commissions'] = app(QuoteCommissionSummaryCalculator::class)->totals($this->resource);
        }

        return $summary;
    }

    /**
     * `gross` is DERIVED at runtime (net + vat), never persisted (D-9).
     *
     * @return array{net: string, vat: string, gross: string}
     */
    private function amountTriplet(string $net, string $vat): array
    {
        return [
            'net' => $net,
            'vat' => $vat,
            'gross' => number_format((float) $net + (float) $vat, 2, '.', ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveApplicableAttributes(): array
    {
        return app(QuoteAttributeResolver::class)
            ->resolve($this->resource)
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }
}
