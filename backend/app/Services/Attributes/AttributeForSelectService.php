<?php

declare(strict_types=1);

namespace App\Services\Attributes;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "Campo attuale" options (spec 0429 D-7): only fields that can be a
 * current one (no current field of their own, D-1), searched by name or code.
 * The requested `ids[]` are appended for edit-mode hydration, as every
 * for-select does (ADR 0011).
 */
final class AttributeForSelectService
{
    private const array COLUMNS = ['id', 'code', 'name'];

    public function forSelect(ForSelectQuery $query, ?int $excludeId): ForSelectResult
    {
        // Step 1: the candidates, narrowed by the search
        $base = Attribute::query()
            ->select(self::COLUMNS)
            ->whereNull('current_attribute_id')
            ->when($excludeId !== null, static fn (Builder $builder) => $builder->whereKeyNot($excludeId))
            ->when($query->hasSearch(), static fn (Builder $builder) => $builder->where(
                static fn (Builder $nested) => $nested->where('name', 'like', '%'.$query->search.'%')->orWhere('code', 'like', '%'.$query->search.'%'),
            ));

        // Step 2: one page, then the hydrated ids it does not hold
        $result = $query->page($base, static fn (Builder $builder): Builder => $builder->orderBy('name')->orderBy('id'));

        return $result->withItems($this->appendHydratedIds($result->items, $query));
    }

    /**
     * @param  Collection<int, mixed>  $page
     * @return Collection<int, mixed>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        $missingIds = $query->hasIds() ? array_values(array_diff($query->ids, $page->pluck('id')->all())) : [];

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat(Attribute::query()->select(self::COLUMNS)->whereKey($missingIds)->orderBy('name')->get());
    }
}
