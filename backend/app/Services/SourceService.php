<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Sources\CreateSourceData;
use App\DataObjects\Sources\UpdateSourceData;
use App\Models\Source;
use Illuminate\Support\Collection;

/**
 * Business logic for the `sources` resource (spec 0018): a plain lookup
 * entity (id, name). The controller stays thin; this Service is the single
 * authority, mirroring ReferentTypeService's simpler slice.
 */
class SourceService
{
    public function create(CreateSourceData $data): Source
    {
        return Source::create($data->attributes());
    }

    public function update(Source $source, UpdateSourceData $data): Source
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $source->fill($attributes)->save();

        return $source->fresh();
    }

    /**
     * Restrictive delete (spec 0024 BR-2/D-4, spec 0040 BR-3): a source
     * referenced by at least one lead, opportunity OR campaign (spec 0176)
     * cannot be removed.
     */
    public function delete(Source $source): void
    {
        if ($source->leads()->exists()) {
            abort(409, 'This source has leads and cannot be deleted.');
        }

        if ($source->opportunities()->exists()) {
            abort(409, 'This source has opportunities and cannot be deleted.');
        }

        if ($source->campaigns()->exists()) {
            abort(409, 'This source has campaigns and cannot be deleted.');
        }

        $source->delete();
    }

    /**
     * Minimal, searchable, paginated source list for the for-select standard
     * (ADR 0011), mirroring ReferentTypeService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Source::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Source> $page */
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
     * @param  Collection<int, Source>  $page
     * @return Collection<int, Source>
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

        /** @var Collection<int, Source> $hydrated */
        $hydrated = Source::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
