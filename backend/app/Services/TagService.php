<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Tags\CreateTagData;
use App\DataObjects\Tags\UpdateTagData;
use App\Models\Tag;
use App\Services\Deletion\InUseGuard;
use Illuminate\Support\Collection;

/**
 * Business logic for the `tags` resource (spec 0019): a plain lookup entity
 * (id, name), mirroring SourceService. The polymorphic tagging of other
 * entities was retired (the `taggables` pivot is dropped), so delete is a
 * plain delete with no association guard.
 */
class TagService
{
    public function __construct(private readonly InUseGuard $inUseGuard) {}

    public function create(CreateTagData $data): Tag
    {
        return Tag::create($data->attributes());
    }

    public function update(Tag $tag, UpdateTagData $data): Tag
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $tag->fill($attributes)->save();

        return $tag->fresh();
    }

    /**
     * Plain delete: the polymorphic tagging relation was retired (the
     * `taggables` pivot is dropped), so there is no association to guard.
     */
    public function delete(Tag $tag): void
    {
        $this->inUseGuard->assertUnused($tag);

        $tag->delete();
    }

    /**
     * Minimal, searchable, paginated tag list for the for-select standard
     * (ADR 0011), mirroring SourceService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Tag::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Tag> $page */
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
     * @param  Collection<int, Tag>  $page
     * @return Collection<int, Tag>
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

        /** @var Collection<int, Tag> $hydrated */
        $hydrated = Tag::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
