<?php

namespace App\Services;

use App\DataObjects\Sectors\CreateSectorData;
use App\DataObjects\Sectors\UpdateSectorData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Sector;
use App\Services\Sectors\SectorActivity;
use App\Services\Sectors\SectorHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `sectors` resource (spec 0018): create/update
 * (including the anti-cycle guard on `parent_id`), a restrictive delete
 * (children only — no other relation exists yet), and the read-side tree
 * (delegated to SectorHierarchy). The controller stays thin; this
 * Service is the single authority.
 */
class SectorService
{
    public function __construct(
        private readonly SectorHierarchy $hierarchy,
        private readonly SectorActivity $activity,
    ) {}

    public function create(CreateSectorData $data): Sector
    {
        return DB::transaction(function () use ($data): Sector {
            /** @var Sector $sector */
            $sector = Sector::create([
                'name' => $data->name,
                'parent_id' => $data->parentId,
                'is_active' => $data->isActive,
            ]);

            return $sector->fresh(['parent']);
        });
    }

    public function update(Sector $sector, UpdateSectorData $data): Sector
    {
        if ($data->hasParentId() && $data->parentId !== null) {
            $this->assertNoCycle($sector, $data->parentId);
        }

        return DB::transaction(function () use ($sector, $data): Sector {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $sector->fill($attributes)->save();

            return $sector->fresh(['parent']);
        });
    }

    /**
     * Restrictive delete: a sector with child sectors cannot be removed (it
     * would silently orphan them). No other relation exists yet (spec 0018
     * scope), unlike ProductCategoryService's product guard.
     */
    public function delete(Sector $sector): void
    {
        if ($sector->children()->exists()) {
            abort(409, 'This sector has sub-sectors and cannot be deleted.');
        }

        $sector->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->hierarchy->tree();
    }

    /**
     * Minimal, searchable, paginated sector list for the for-select
     * standard (ADR 0011, spec 0020), mirroring SourceService::forSelect.
     * The effectively inactive sectors (spec 0212 D-1) are never offered and
     * never counted; `ids[]` still hydrates them (D-2).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Sector::query()
            ->select(['id', 'name'])
            ->whereNotIn('id', $this->activity->inactiveSectorIds());

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Sector> $page */
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
     * @param  Collection<int, Sector>  $page
     * @return Collection<int, Sector>
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

        /** @var Collection<int, Sector> $hydrated */
        $hydrated = Sector::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * $parentId may not be $sector itself, nor one of its own descendants
     * (i.e. $sector may not be an ancestor of the prospective new parent) —
     * either would create a cycle in the tree.
     */
    private function assertNoCycle(Sector $sector, int $parentId): void
    {
        if ($parentId === $sector->id) {
            abort(422, 'A sector cannot be its own parent.');
        }

        $parent = Sector::find($parentId);

        if ($parent !== null && $this->hierarchy->isAncestorOf($parent, $sector->id)) {
            abort(422, 'A sector cannot be moved under one of its own descendants.');
        }
    }
}
