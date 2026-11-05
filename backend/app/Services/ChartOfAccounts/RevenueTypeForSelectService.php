<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\RevenueType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Selectable revenue sub-accounts for the pickers (spec 0350, D-10): active
 * ones under an active account and group, naturally ordered by code
 * (`70.2` before `70.10`), searched on code and name. `ids[]` hydrates
 * existing selections even when inactive and does not inflate the total. The
 * chart is small, so ordering and paging happen in memory.
 */
class RevenueTypeForSelectService
{
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        // Step 1: the selectable population, naturally ordered
        $selectable = $this->selectable($query)->get()
            ->sort(static fn (RevenueType $a, RevenueType $b): int => strnatcasecmp($a->code, $b->code))->values();

        // Step 2: the requested window, then the hydrated ids not already in it
        $page = $selectable->slice($query->offset, $query->limit)->values();

        return new ForSelectResult($this->withHydratedIds($page, $query), $selectable->count(), $query->offset, $query->limit);
    }

    /**
     * @return Builder<RevenueType>
     */
    private function selectable(ForSelectQuery $query): Builder
    {
        return RevenueType::query()->with(RevenueTypeRef::RELATION)
            ->where('is_active', true)
            ->whereHas('chartAccount', static fn (Builder $account) => $account->where('is_active', true)
                ->whereHas('parent', static fn (Builder $group) => $group->where('is_active', true)))
            ->when($query->hasSearch(), static fn (Builder $revenueTypes) => $revenueTypes->where(
                static fn (Builder $match) => $match->where('code', 'like', "%{$query->search}%")->orWhere('name', 'like', "%{$query->search}%"),
            ));
    }

    /**
     * @param  Collection<int, RevenueType>  $page
     * @return Collection<int, RevenueType>
     */
    private function withHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        $missing = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missing === []) {
            return $page;
        }

        return $page->concat(RevenueType::query()->with(RevenueTypeRef::RELATION)->whereIn('id', $missing)->get())->values();
    }
}
