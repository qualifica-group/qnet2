<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * GET /api/quotes/for-select (ADR 0011), feeding the `rewarded-referents`
 * "Offerta" advanced filter (spec 0059 amendment A-01, user directive
 * 2026-08-31 — the counterpart of the "Opportunita'" filter, now that a buono
 * can be born on an Offerta).
 *
 * Its OWN class rather than a method on QuoteService, which is already at 487
 * of the 500-line hard ceiling (engineering.md §6): a for-select is a
 * self-contained read with no overlap with that service's write lifecycle.
 * The search/pagination/hydration semantics are the shared ones every other
 * for-select implements (mirrors OpportunityService::forSelect verbatim, only
 * the searchable columns differ).
 */
final class QuoteForSelectService
{
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->baseQuery();

        if ($query->hasSearch()) {
            // An offer is looked up by its sequential `code` as often as by
            // its title, so both are searchable — grouped so the OR can never
            // escape a future outer constraint.
            $base->where(function (Builder $scoped) use ($query): void {
                $scoped->where('code', 'like', '%'.$query->search.'%')
                    ->orWhere('title', 'like', '%'.$query->search.'%');
            });
        }

        // Client filter (spec 0199): an Offerta has no `registry_id` of its
        // own — it is its Opportunity's. Bypassed for the ids[] hydration
        // below exactly like `search` is.
        if ($query->registryId !== null) {
            $base->whereHas('opportunity', function (Builder $scoped) use ($query): void {
                $scoped->where('registry_id', $query->registryId);
            });
        }

        $window = $query->page($base, static fn ($ordered) => $ordered->orderBy('code')->orderBy('id'));

        /** @var Collection<int, Quote> $page */
        $page = $window->items;

        return $window->withItems($this->appendHydratedIds($page, $query));
    }

    /**
     * @return Builder<Quote>
     */
    private function baseQuery(): Builder
    {
        return Quote::query()->select(['id', 'code', 'title']);
    }

    /**
     * Edit-mode hydration (ADR 0011): the ids the client already holds are
     * appended deduplicated, bypassing the search filter and never inflating
     * `total`.
     *
     * @param  Collection<int, Quote>  $page
     * @return Collection<int, Quote>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Quote> $hydrated */
        $hydrated = $this->baseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
