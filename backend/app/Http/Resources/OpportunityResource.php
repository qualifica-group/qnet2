<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Opportunity
 *
 * `locked_fields` (spec 0040, BR-2) is [] when the opportunity has no lead,
 * else re-resolved from the CURRENT lead/campaign state via
 * LeadOpportunityDefaultsResolver — the same single source of truth the
 * write-path lock enforcement uses. Relies on OpportunityService::loadDetail()
 * having eager-loaded every relation this touches (including the lead's own
 * chain), so resolving any of them here never N+1s.
 *
 * Amendment rev.3: `business_function_id`/`business_function`/
 * `product_category_id`/`product_category` are REPLACED by `product_lines`
 * (one row per funzione-aziendale + categoria-prodotto pair). User directive
 * 2026-07-17: `company_id`/`company`/`company_site_id`/`company_site` are
 * REMOVED entirely.
 * `opportunity_status_id`/`opportunity_status` (spec 0043, D-3) is the
 * mandatory working-state FK — NEVER null.
 *
 * Spec 0056: `operational_site_id`/`operational_site` are reintroduced — the
 * site has no own `name` (only `id`/`old_id`/`alias`), so `operational_site`
 * is `{id, label}` (OperationalSiteLabel, NOT summarizeByName()), the label
 * composed server-side "{line1} - {city}". Relies on
 * OpportunityService::DETAIL_RELATIONS eager-loading
 * `operationalSite.addresses.city`.
 *
 * Spec 0047: `state`/`state_id` is the Regione (D1); `workflow_status`/
 * `opportunity_workflow_status_id` is the currently resolved working-state
 * row (the NEW dimension, distinct from `opportunity_status`);
 * `workflow_statuses` is the full ordered set OpportunityWorkflowResolver
 * resolves for THIS opportunity right now (for the FE's status select,
 * limited to that set). Resolving the set re-runs the resolver (a bounded,
 * controlled query), relying on `productLines` already being eager-loaded by
 * OpportunityService::loadDetail() so it never N+1s beyond that one query.
 *
 * Spec 0049, D-8/AC-050 (additive, retrocompatible): `attribute_values` is the
 * raw opportunity-level values map (`{}` when null) and `applicable_attributes`
 * is the union/dedup-by-code set of the product lines' effective category
 * attributes (App\RequestManagement\ApplicableAttributesResolver — same
 * resolver the request-management module uses). Relies on
 * OpportunityService::loadDetail() already eager-loading
 * `productLines.productCategory`, so resolving it here never N+1s.
 *
 * Spec 0059: `rewards`, ordered by `reward_type.name` (data contract), feeds
 * the form's edit-mode hydration for the "abbinamento buono" control. Relies
 * on OpportunityService::DETAIL_RELATIONS eager-loading `rewards.rewardType`.
 */
class OpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'registry_id' => $this->registry_id,
            'registry' => $this->summarizeByName($this->registry),
            'referent_id' => $this->referent_id,
            'referent' => $this->summarizeByName($this->referent),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->summarizeByName($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->summarizeByName($this->reporter),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->summarizeByName($this->supervisor),
            'source_id' => $this->source_id,
            'source' => $this->summarizeByName($this->source),
            'operational_site_id' => $this->operational_site_id,
            'operational_site' => OperationalSiteLabel::summarize($this->operationalSite),
            'opportunity_status_id' => $this->opportunity_status_id,
            'opportunity_status' => $this->summarizeStatus($this->opportunityStatus),
            'state_id' => $this->state_id,
            'state' => $this->summarizeByName($this->state),
            'opportunity_workflow_status_id' => $this->opportunity_workflow_status_id,
            'workflow_status' => $this->summarizeWorkflowStatus($this->workflowStatus),
            'workflow_statuses' => $this->resolveWorkflowStatuses(),
            'product_lines' => $this->summarizeProductLines($this->productLines),
            'products_of_interest' => $this->summarizeProductsOfInterest($this->productsOfInterest),
            'rewards' => $this->summarizeRewards($this->rewards),
            'lead_id' => $this->lead_id,
            'lead' => $this->summarizeLead($this->lead),
            'managers' => $this->summarizeManagers($this->managers),
            'start_date' => $this->start_date,
            'estimated_value' => $this->estimated_value,
            'expected_close_date' => $this->expected_close_date,
            'success_probability' => $this->success_probability,
            'general_notes' => $this->general_notes,
            'locked_fields' => $this->resolveLockedFields(),
            'attribute_values' => $this->attribute_values ?? [],
            'applicable_attributes' => $this->resolveApplicableAttributes(),
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
     * The opportunity_status summary (spec 0043, D-3, mandatory: NEVER null
     * on a persisted opportunity), including `color` for the FE badge.
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeStatus(?Model $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function summarizeLead(mixed $lead): ?array
    {
        return $lead === null ? null : ['id' => $lead->id, 'label' => $lead->registry?->name ?? ''];
    }

    /**
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
     * "Prodotti di interesse" (user directive 2026-07-22): identical shape to
     * RequestManagementResource's, so the opportunity card and the work panel
     * read the collection the same way.
     *
     * @return array<int, array{id: int, name: string, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductsOfInterest(iterable $products): array
    {
        return collect($products)
            ->map(fn (Model $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'product_category' => $this->summarizeByName($product->category),
            ])
            ->values()
            ->all();
    }

    /**
     * Reward assignments (spec 0059), ordered by `reward_type.name` (data
     * contract).
     *
     * @return array<int, array{id: int, reward_type: array{id: int, name: string, color: string}, assigned_at: string|null, notes: string|null}>
     */
    private function summarizeRewards(iterable $rewards): array
    {
        return collect($rewards)
            ->sortBy(fn (Model $reward): string => $reward->rewardType->name)
            ->values()
            ->map(fn (Model $reward): array => [
                'id' => $reward->id,
                'reward_type' => [
                    'id' => $reward->rewardType->id,
                    'name' => $reward->rewardType->name,
                    'color' => $reward->rewardType->color,
                ],
                'assigned_at' => $reward->assigned_at?->toDateString(),
                'notes' => $reward->notes,
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
     * The currently resolved working-state row (spec 0047), including
     * `system_key`/`group` so the FE can tell a pinned system row apart from
     * a custom one.
     *
     * @return array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, group: string, requires_note: bool}|null
     */
    private function summarizeWorkflowStatus(?OpportunityWorkflowStatus $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'description' => $status->description,
            'color' => $status->color,
            'system_key' => $status->system_key,
            'group' => $status->group->value,
            'requires_note' => $status->requires_note,
        ];
    }

    /**
     * The full ordered set OpportunityWorkflowResolver resolves for this
     * opportunity RIGHT NOW (spec 0047) — feeds the FE's "stato di
     * lavorazione" select, limited to that set (AC-017).
     *
     * @return array<int, array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, group: string, requires_note: bool}>
     */
    private function resolveWorkflowStatuses(): array
    {
        $resolver = app(OpportunityWorkflowResolver::class);

        return $resolver->statusesFor($resolver->resolve($this->resource))
            ->map(fn (OpportunityWorkflowStatus $status): array => $this->summarizeWorkflowStatus($status))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveLockedFields(): array
    {
        if ($this->lead_id === null) {
            return [];
        }

        $lead = $this->lead;

        if ($lead === null) {
            return [];
        }

        return app(LeadOpportunityDefaultsResolver::class)->resolve($lead)->lockedFields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveApplicableAttributes(): array
    {
        return app(ApplicableAttributesResolver::class)
            ->resolve($this->resource)
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }
}
