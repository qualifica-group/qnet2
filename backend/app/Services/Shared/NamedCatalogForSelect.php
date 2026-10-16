<?php

namespace App\Services\Shared;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * for-select query shared by the name-only HR catalogues (spec 0271, ADR 0011):
 * search by name, order by name/id, and the requested `ids[]` (edit-mode
 * hydration) appended deduplicated without inflating the total.
 */
final class NamedCatalogForSelect
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function run(string $modelClass, ForSelectQuery $query): ForSelectResult
    {
        $base = $modelClass::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Model> $page */
        $page = $this->ordered($base)->offset($query->offset)->limit($query->limit)->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($modelClass, $page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->orderBy('name')->orderBy('id');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Collection<int, Model>  $page
     * @return Collection<int, Model>
     */
    private function appendHydratedIds(string $modelClass, Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat($this->ordered($modelClass::query()->select(['id', 'name']))->whereIn('id', $missingIds)->get());
    }
}
