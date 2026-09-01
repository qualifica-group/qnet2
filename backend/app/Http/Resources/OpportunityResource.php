<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\SummarizesRewards;
use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\OpportunityManagerLabelResolver;
use App\Services\Opportunities\OpportunityQuoteLimit;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Services\Quotes\QuoteManagerSyncMode;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
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
 * Spec 0082/0083: `opportunity_status_id`/`opportunity_status` are REMOVED
 * and replaced by `status`, the COMPUTED summary
 * (App\Services\Opportunities\OpportunityStatusResolver) read off the
 * opportunity's quotes, falling back to the GLOBAL default quote-workflow
 * `open` row when it has none (D-8). Spec 0083, D-2: the Opportunity carries
 * no working-state FK of its own any more — the former workflow-status
 * override column, `workflow_status` and `workflow_statuses` (spec 0047) are
 * REMOVED outright, the configurator having moved onto the Offerta (->
 * Quote).
 *
 * Spec 0056: `operational_site_id`/`operational_site` are reintroduced — the
 * site has no own `name` (only `id`/`old_id`/`alias`), so `operational_site`
 * is `{id, label}` (OperationalSiteLabel, NOT summarizeByName()), the label
 * composed server-side "{line1} - {city}". Relies on
 * OpportunityService::DETAIL_RELATIONS eager-loading
 * `operationalSite.addresses.city`.
 *
 * Spec 0084, D-1: the former `attribute_values`/`applicable_attributes`/
 * `attribute_layout` trio (spec 0049/user directive 2026-08-05) is REMOVED —
 * the dynamic "Informazioni aggiuntive" section moved to the Offerta (Quote),
 * see QuoteResource.
 *
 * Spec 0059: `rewards`, ordered by `reward_type.name` (data contract), feeds
 * the form's edit-mode hydration for the "abbinamento buono" control. Relies
 * on OpportunityService::DETAIL_RELATIONS eager-loading `rewards.rewardType`.
 *
 * Spec 0067: `quotes_count` is the explicit inverse-relation counter feeding
 * the Offerte panel's initial header value, before the panel's own grid
 * reports `pagination.total`. Relies on OpportunityService::loadDetail()
 * always calling `loadCount('quotes')`, so it is never missing here.
 *
 * Spec 0083, D-5: `requires_quote` is REMOVED — the Offerte panel's gate on
 * it is dropped, every Opportunity may carry offers.
 *
 * Spec 0085 amendment (2026-08-06): the compact `{id, code, title}` per
 * Offerta is NO LONGER projected here. It feeds the notes selectors only,
 * and those now read it from the notes response itself (`meta.quotes`,
 * NotableEntity::quoteScopes) — the surfaces mounting the component from a
 * grid row have no opportunity detail to read it from, and two projections
 * of the same thing would drift apart at the first change.
 *
 * Spec 0080: `manager_labels` is ADDITIVE — the per-position "Gestore
 * Account" denomination overrides resolved from the product line(s)' product
 * category (OpportunityManagerLabelResolver), `{}` when not resolvable.
 * `managers`/`manager_slots` stay byte-for-byte identical: this is purely a
 * denomination layer alongside them.
 *
 * #[PreserveKeys]: `manager_labels` is a sparse position("1".."4")->label
 * map — JsonResource's default filter() reindexes any NESTED array whose
 * keys are ALL numeric, which would silently turn `{"2":"Operatore"}` into
 * `["Operatore"]` on the wire. Every other array field here is already
 * 0-indexed-sequential, so this is a no-op for them.
 *
 * Spec 0087, D-7: `managers_synchronized` is ADDITIVE — the QuoteResource
 * counterpart's own field, feeding the "team sincronizzato" banner on THIS
 * side of the pair too: true when a write on either this Opportunity or its
 * (at most one) Quote replaces the other's GA list wholesale.
 */
#[PreserveKeys]
class OpportunityResource extends JsonResource
{
    use SummarizesRewards;

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
            'status' => app(OpportunityStatusResolver::class)->resolve($this->resource),
            'product_lines' => $this->summarizeProductLines($this->productLines),
            'products_of_interest' => $this->summarizeProductsOfInterest($this->productsOfInterest),
            'rewards' => $this->summarizeRewards($this->rewards),
            'lead_id' => $this->lead_id,
            'lead' => $this->summarizeLead($this->lead),
            'managers' => $this->summarizeManagers($this->managers),
            'manager_labels' => app(OpportunityManagerLabelResolver::class)->resolve($this->resource),
            'managers_synchronized' => app(QuoteManagerSyncMode::class)->isSynchronized($this->resource),
            'start_date' => $this->start_date,
            'estimated_value' => $this->estimated_value,
            'expected_close_date' => $this->expected_close_date,
            'success_probability' => $this->success_probability,
            'general_notes' => $this->general_notes,
            'quotes_count' => (int) ($this->quotes_count ?? 0),
            // User directive 2026-08-07: whether this opportunity's product
            // category caps it at ONE offer. Ships the RULE, not the verdict —
            // the panel pairs it with the live row count it already tracks, so
            // deleting the only offer re-enables "Crea Offerta" with no
            // refetch. StoreQuoteRequest stays the authority (422).
            'single_quote_per_opportunity' => app(OpportunityQuoteLimit::class)->isSingleQuoteBranch($this->resource),
            'locked_fields' => $this->resolveLockedFields(),
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
}
