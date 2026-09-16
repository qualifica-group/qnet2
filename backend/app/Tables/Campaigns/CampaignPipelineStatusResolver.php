<?php

namespace App\Tables\Campaigns;

use App\Models\Campaign;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `campaigns` domain's doubly-derived `pipeline_status` column
 * (BR-2/AC-032), extracted out of CampaignsTableDefinition (file-size split,
 * engineering.md §6): a linked campaign always has its OWN
 * `pipeline_status_id` NULL, so its EFFECTIVE status is the campaign's own
 * when standalone, else read through the linked project's — for a single row
 * (mapRow), for the Set Filter (bound nested whereHas, no raw SQL) and for
 * the Excel-like distinct value list.
 */
final class CampaignPipelineStatusResolver
{
    use HandlesBlankSetFilter;

    /**
     * Maximum values honoured in the advanced-filter id set. Mirrors
     * AdvancedFilterApplier::MAX_VALUES — this bypasses that generic
     * relation-by-id path (BR-2), so it caps the WHERE IN cardinality itself.
     */
    private const int MAX_ADVANCED_FILTER_VALUES = 500;

    /**
     * The campaign's EFFECTIVE pipeline_status: its own when standalone, else
     * read through the linked project's (relations must already be
     * eager-loaded by the caller's baseQuery()).
     */
    public function effectiveStatus(Campaign $row): ?Model
    {
        return $row->pipelineStatus ?? $row->project?->pipelineStatus;
    }

    /**
     * Match campaigns whose EFFECTIVE pipeline_status name is among $names —
     * own status (standalone) OR the linked project's, combined via a nested
     * `whereHas('project.pipelineStatus', ...)` (bound parameters only).
     *
     * @param  Builder<Campaign>  $query
     * @param  array<int, string>  $names
     */
    public function applyFilter(Builder $query, array $names, bool $matchesBlank = false): void
    {
        if ($names === [] && ! $matchesBlank) {
            return;
        }

        $query->where(function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('pipelineStatus', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                })->orWhereHas('project.pipelineStatus', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): no own status and none through the
            // linked project either.
            if ($matchesBlank) {
                $group->orWhere(static function (Builder $blank): void {
                    $blank->whereDoesntHave('pipelineStatus')->whereDoesntHave('project.pipelineStatus');
                });
            }
        });
    }

    /**
     * Match campaigns whose EFFECTIVE pipeline_status id is among $ids — own
     * status (standalone) OR the linked project's, same own-or-through-project
     * (BR-2) semantics as applyFilter() above. Advanced-filter (spec 0032)
     * sibling: the panel's `relation` widget submits ids, not names.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<int, mixed>  $ids
     */
    public function applyIdFilter(Builder $query, array $ids): void
    {
        $ids = array_slice(
            array_values(array_filter($ids, static fn (mixed $id): bool => is_scalar($id))),
            0,
            self::MAX_ADVANCED_FILTER_VALUES,
        );

        if ($ids === []) {
            return;
        }

        $query->where(function (Builder $group) use ($ids): void {
            $group->whereHas('pipelineStatus', static function (Builder $relatedQuery) use ($ids): void {
                $relatedQuery->whereIn('id', $ids);
            })->orWhereHas('project.pipelineStatus', static function (Builder $relatedQuery) use ($ids): void {
                $relatedQuery->whereIn('id', $ids);
            });
        });
    }

    /**
     * Distinct EFFECTIVE pipeline_status names among the campaigns matching
     * $query (already scoped by every other active filter): own status ids
     * (standalone) union the linked projects' status ids.
     *
     * @param  Builder<Campaign>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $ownStatusIds = (clone $query)->whereNotNull('pipeline_status_id')->select('pipeline_status_id');
        $linkedProjectIds = (clone $query)->whereNull('pipeline_status_id')->whereNotNull('project_id')->pluck('project_id');

        $linkedStatusIds = DB::table('projects')
            ->whereIn('id', $linkedProjectIds)
            ->whereNotNull('pipeline_status_id')
            ->pluck('pipeline_status_id');

        $values = DB::table('pipeline_statuses')
            ->where(function ($builder) use ($ownStatusIds, $linkedStatusIds): void {
                $builder->whereIn('id', $ownStatusIds)->orWhereIn('id', $linkedStatusIds);
            })
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('pipelineStatus')
            ->whereDoesntHave('project.pipelineStatus')
            ->exists());
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
