<?php

namespace App\Services;

use App\DataObjects\ReferentTypes\CreateReferentTypeData;
use App\DataObjects\ReferentTypes\UpdateReferentTypeData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ReferentType;
use App\Services\Deletion\InUseGuard;
use Illuminate\Support\Collection;

/**
 * Business logic for the `referent-types` resource (spec 0016): a plain
 * lookup entity (id, name). The controller stays thin; this Service is the
 * single authority, mirroring BusinessFunctionService's simpler slice.
 */
class ReferentTypeService
{
    public function __construct(private readonly InUseGuard $inUseGuard) {}

    public function create(CreateReferentTypeData $data): ReferentType
    {
        return ReferentType::create($data->attributes());
    }

    public function update(ReferentType $referentType, UpdateReferentTypeData $data): ReferentType
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $referentType->fill($attributes)->save();

        return $referentType->fresh();
    }

    /**
     * Deleting a type never cascades: referents pointing at it just see
     * `referent_type_id` turn null (migration nullOnDelete).
     */
    public function delete(ReferentType $referentType): void
    {
        $this->inUseGuard->assertUnused($referentType);

        $referentType->delete();
    }

    /**
     * Minimal, searchable, paginated referent-type list for the for-select
     * standard (spec 0015, ADR 0011), mirroring BusinessFunctionService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ReferentType::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ReferentType> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, ReferentType>  $page
     * @return Collection<int, ReferentType>
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

        /** @var Collection<int, ReferentType> $hydrated */
        $hydrated = ReferentType::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
