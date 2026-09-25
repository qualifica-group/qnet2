<?php

namespace App\Http\Resources;

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TableFilterView
 */
class TableFilterViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owned = $this->user_id === $request->user()?->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => (object) $this->filters,
            // Advanced filters (spec 0032) captured by this saved view.
            'advanced_filters' => (object) ($this->advanced_filters ?? []),
            // Custom filter rules (spec 0158): {and, or} | null.
            'rules' => $this->rules !== null ? [
                'and' => array_values($this->rules['and'] ?? []),
                'or' => array_values($this->rules['or'] ?? []),
            ] : null,
            'visibility' => $this->visibility->value,
            'owned' => $owned,
            // Only surfaced for a shared view NOT owned by the actor ("shared by
            // X"). Display name only — never the owner's email/PII.
            'owner_name' => (! $owned && $this->visibility === FilterViewVisibility::Shared)
                ? $this->user->name
                : null,
            'is_favorite' => $this->resolveIsFavorite($request->user()?->id),
        ];
    }

    /**
     * Prefers the eagerly-computed `is_favorite` attribute (TableFilterViewService::list()'s
     * `withExists`, batched for the whole list — no N+1); falls back to a
     * single live exists() check for a single-row response (create/update/
     * favorite/unfavorite), where one extra query is not a scaling concern.
     */
    private function resolveIsFavorite(?int $actorId): bool
    {
        if (array_key_exists('is_favorite', $this->resource->getAttributes())) {
            return (bool) $this->is_favorite;
        }

        return $actorId !== null && $this->favoritedByUsers()->where('user_id', $actorId)->exists();
    }
}
