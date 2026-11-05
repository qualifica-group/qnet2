<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\TreasuryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Selectable financial sub-accounts for the pickers (spec 0355, D-3): active
 * ones under an active account and group, naturally ordered by code
 * (`70.2` before `70.10`), searched on code and name. `ids[]` hydrates
 * existing selections even when inactive and does not inflate the total. The
 * chart is small, so ordering and paging happen in memory.
 */
class TreasuryTypeForSelectService
{
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        // Step 1: the selectable population, naturally ordered
        $selectable = $this->selectable($query)->get()
            ->sort(static fn (TreasuryType $a, TreasuryType $b): int => strnatcasecmp($a->code, $b->code))->values();

        // Step 2: the requested window, then the hydrated ids not already in it
        $page = $selectable->slice($query->offset, $query->limit)->values();

        return new ForSelectResult($this->withHydratedIds($page, $query), $selectable->count(), $query->offset, $query->limit);
    }

    /**
     * @return Builder<TreasuryType>
     */
    private function selectable(ForSelectQuery $query): Builder
    {
        return TreasuryType::query()->with(TreasuryTypeRef::RELATION)
            ->where('is_active', true)
            ->whereHas('chartAccount', static fn (Builder $account) => $account->where('is_active', true)
                ->whereHas('parent', static fn (Builder $group) => $group->where('is_active', true)))
            ->when($query->hasSearch(), static fn (Builder $treasuryTypes) => $treasuryTypes->where(
                static fn (Builder $match) => $match->where('code', 'like', "%{$query->search}%")->orWhere('name', 'like', "%{$query->search}%"),
            ));
    }

    /**
     * @param  Collection<int, TreasuryType>  $page
     * @return Collection<int, TreasuryType>
     */
    private function withHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        $missing = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missing === []) {
            return $page;
        }

        return $page->concat(TreasuryType::query()->with(TreasuryTypeRef::RELATION)->whereIn('id', $missing)->get())->values();
    }
}
