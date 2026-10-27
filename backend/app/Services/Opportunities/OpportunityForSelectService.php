<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * GET /api/opportunities/for-select (ADR 0011): its OWN class rather than
 * methods on OpportunityService, which sits at the 500-line hard ceiling
 * (engineering.md §6); a for-select is a self-contained read with no overlap
 * with that service's write lifecycle (precedent: QuoteForSelectService).
 * Rows are narrowed by OpportunityVisibilityScope (spec 0316).
 */
final class OpportunityForSelectService
{
    /**
     * Minimal, searchable, paginated opportunity list for the for-select
     * standard (ADR 0011, MT-10/spec 0059 — feeds the `rewarded-referents`
     * "opportunity" advanced filter). Searches on `name` directly: unlike
     * Lead, an Opportunity owns its own descriptive column (spec 0057, D-5:
     * `OPP_{id}`), so no registry subquery is needed.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->baseQuery();

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        // Cascading client filter (spec 0122, D-5): additive, bypassed for
        // the explicit ids[] hydration below exactly like `search` is — the
        // edit-mode value must keep showing even if the client filter would
        // otherwise exclude it.
        if ($query->registryId !== null) {
            $base->where('registry_id', $query->registryId);
        }

        $window = $query->page($base, static fn ($ordered) => $ordered->orderBy('name')->orderBy('id'));

        /** @var Collection<int, Opportunity> $page */
        $page = $window->items;

        $items = $this->appendHydratedIds($page, $query);

        return $window->withItems($items);
    }

    /**
     * Base for-select query: opportunities with the three commercial roles
     * AND the sede operativa eager-loaded, so OpportunityForSelectResource's
     * `meta` (the Quote snapshot prefill, spec 0065 D-3 + directive
     * 2026-07-30) never N+1s.
     *
     * @return Builder<Opportunity>
     */
    private function baseQuery(): Builder
    {
        // Spec 0316: narrowed to the actor's visible opportunities; the ids[]
        // hydration reuses this base, so a hidden id never leaks through it.
        return OpportunityVisibilityScope::scopeToActor(Opportunity::query(), Auth::user())
            ->select(['opportunities.id', 'opportunities.name', 'commercial_id', 'reporter_id', 'supervisor_id', 'operational_site_id'])
            ->with([
                'commercial:id,name', 'reporter:id,name', 'supervisor:id,name',
                'managers:id,name',
                // The sede operativa's label is composed from its primary
                // address + city (it has no name column of its own).
                'operationalSite.addresses.city',
            ]);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name/meta projection applies. Total is unaffected.
     *
     * @param  Collection<int, Opportunity>  $page
     * @return Collection<int, Opportunity>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Opportunity> $hydrated */
        $hydrated = $this->baseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
