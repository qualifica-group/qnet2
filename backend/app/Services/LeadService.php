<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Leads\ConvertLeadToOpportunity;
use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Leads\UpdateLeadData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Lead;
use App\Models\User;
use App\Services\Leads\LeadProductInterestWriter;
use App\Services\Opportunities\ProductCategoryCoherence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `leads` resource (spec 0024): plain create/update/
 * delete. No sequential code (D-3, unlike Project/Campaign); the cross-entity
 * guard blocking cancellation of a lead's OWN referenced entities (BR-2/D-4)
 * lives in the 5 REFERENCED modules' Services, not here (see
 * CampaignService/RegistryService/OperationalSiteService/SourceService/
 * UserService — spec 0041 D-1: the contact guard moved from Referent to
 * Registry). delete() carries its own guard for the INVERSE direction
 * (spec 0040, BR-3): a lead with a linked opportunity cannot itself be
 * removed.
 */
class LeadService
{
    /**
     * Relations eager-loaded for the detail read tree (LeadResource), so a
     * single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        // The anagrafica's personal-data card comes along so LeadResource can
        // project its PRIMARY contacts (call/mail straight from the record
        // card) without a second query.
        'registry.personalData.contacts',
        'campaign',
        'operationalSite.addresses.city',
        'source',
        'operator',
        // spec 0040: LeadResource.opportunity {id,name}|null.
        'opportunity',
        // spec 0094 (AC-030): LeadResource.products_of_interest[], each with
        // its category — never lazy-loaded (preventLazyLoading).
        'productsOfInterest.category',
    ];

    public function __construct(
        private readonly ConvertLeadToOpportunity $converter,
        private readonly LeadProductInterestWriter $productInterestWriter,
        // AC-034: the OTHER half of the coherence rule (mirrors
        // OpportunityService's own pairing) — a `campaign_id` change that
        // orphans a persisted product of interest, checked only when
        // `products_of_interest` did NOT travel in the same payload.
        private readonly ProductCategoryCoherence $coherence,
    ) {}

    public function loadDetail(Lead $lead): Lead
    {
        return $lead->load(self::DETAIL_RELATIONS);
    }

    /**
     * Plain create, or — when `convertToOpportunity` is set (spec 0044) — the
     * Lead and its derived Opportunity created atomically in a single
     * transaction: the conversion logic itself lives entirely in
     * ConvertLeadToOpportunity, not unrolled here.
     */
    public function create(CreateLeadData $data, ?User $actor = null): Lead
    {
        $lead = DB::transaction(function () use ($data, $actor): Lead {
            $lead = Lead::create($data->attributes());

            // "Prodotti di interesse" (spec 0094, D-5): synced before a
            // possible conversion, so ConvertLeadToOpportunity always finds
            // the final, coherence-checked set already persisted.
            if ($data->hasProductsOfInterest()) {
                $this->productInterestWriter->sync($lead, $data->productsOfInterest);
            }

            if ($data->convertToOpportunity) {
                $this->converter->handle($lead, $actor);
            }

            return $lead;
        });

        return $this->loadDetail($lead);
    }

    public function update(Lead $lead, UpdateLeadData $data): Lead
    {
        $attributes = $data->submittedAttributes();

        DB::transaction(function () use ($lead, $data, $attributes): void {
            // AC-034: captured BEFORE the save() overwrites `campaign_id`,
            // so this only fires on a GENUINE change (not a resubmission of
            // the same value).
            $campaignChanging = $data->campaignIdSubmitted && $data->campaignId !== $lead->campaign_id;

            // Unconditional save: fire the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $lead->fill($attributes)->save();

            if ($data->hasProductsOfInterest()) {
                $this->productInterestWriter->sync($lead, $data->productsOfInterest);
            } elseif ($campaignChanging) {
                // AC-034: the OTHER half of the rule (mirrors
                // OpportunityService::assertPersistedProductsStayCovered) —
                // a PATCH that only re-points `campaign_id` must not leave
                // the PERSISTED products uncovered by the NEW campaign. The
                // 422 lands on `campaign_id`, the key the actor actually
                // submitted (D-5) — no silent removal.
                $this->assertPersistedProductsStayCovered($lead);
            }
        });

        return $this->loadDetail($lead);
    }

    /**
     * @throws ValidationException a persisted product of interest is left uncovered by the new campaign
     */
    private function assertPersistedProductsStayCovered(Lead $lead): void
    {
        $this->coherence->assert(
            $lead->productsOfInterest()->pluck('products.id')->map(intval(...))->all(),
            $this->productInterestWriter->coveredCategoryIds($lead),
            'campaign_id',
            ProductCategoryCoherence::LEAD_MESSAGE,
        );
    }

    /**
     * Restrictive delete (spec 0040, BR-3): a lead with a linked opportunity
     * cannot be removed.
     */
    public function delete(Lead $lead): void
    {
        if ($lead->opportunity()->exists()) {
            abort(409, 'This lead has a linked opportunity and cannot be deleted.');
        }

        $lead->delete();
    }

    /**
     * Minimal, searchable, paginated lead list for the for-select standard
     * (amendment rev.1 A-1, ADR 0011) — a Lead has no own name column, so
     * search/order both go through the registry's name (spec 0041 D-1;
     * mirrors OperationalSiteService::forSelect's primary-address subquery
     * pattern). Feeds the Opportunity form's "Lead" select (spec 0040) and
     * the Task form's own "Lead" select (spec 0154, D-4), the latter via
     * `registryId` — narrows to ONE anagrafica, null means no filter, the
     * same retrocompatible `registryId` convention as
     * `WorkOrderService::forSelect`.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Lead::query()->with(['registry', 'campaign']);

        if ($query->registryId !== null) {
            $base->where('registry_id', $query->registryId);
        }

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->whereHas('registry', function (Builder $registryQuery) use ($term): void {
                $registryQuery->where('name', 'like', $term);
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Lead> $page */
        $page = $base->orderBy($this->registryNameSubquery())
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedForSelectIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Correlated subquery selecting the lead's registry name, the ORDER BY
     * key for for-select (a Lead has no own name column). A plain query
     * builder (DB::table), NOT an Eloquent one — `orderBy()` accepts either.
     */
    private function registryNameSubquery(): QueryBuilder
    {
        return DB::table('registries')
            ->select('name')
            ->whereColumn('registries.id', 'leads.registry_id')
            ->limit(1);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * relations are eager-loaded. Total is unaffected.
     *
     * @param  Collection<int, Lead>  $page
     * @return Collection<int, Lead>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Lead> $hydrated */
        $hydrated = Lead::query()
            ->with(['registry', 'campaign'])
            ->whereIn('id', $missingIds)
            ->orderBy($this->registryNameSubquery())
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
