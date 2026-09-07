<?php

namespace App\Services\ActivityLog;

use App\DataObjects\ActivityLog\ActivityLogCursor;
use App\DataObjects\ActivityLog\ActivityLogPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Aggregates activity_log entries for a root model plus the relations its
 * resource declares in config/activity-log.php (spec 0034), keeping each
 * entry's provenance (module/subject_id) and paginating by keyset.
 *
 * Fully generic: no per-module logic. The root model must already be
 * eager-loaded with the declared relations (the controller resolves it that
 * way), so walking them here never issues an extra query.
 */
final class AggregatedActivityService
{
    public function __construct(private readonly ForeignKeyLabelResolver $labelResolver) {}

    /**
     * @param  array<int, string>  $relations  dot-path relations declared for this resource
     * @param  string|null  $event  narrows the feed to a single event (allow-listed by the FormRequest); null = every event
     */
    public function paginate(
        Model $root,
        array $relations,
        int $perPage,
        ?ActivityLogCursor $cursor,
        ?string $event = null,
    ): ActivityLogPage {
        // Step 1: collect the allow-listed (module alias, subject id) pairs
        $subjectIdsByAlias = $this->collectSubjectIds($root, $relations);

        // Step 2: query only those subjects, keyset-paginated (created_at desc, id desc)
        $query = $this->subjectsQuery($subjectIdsByAlias)->with('causer');
        $this->applyEvent($query, $event);
        $this->applyCursor($query, $cursor);

        // Step 3: fetch one extra row to detect whether a next page exists
        $activities = $query->orderByDesc('created_at')->orderByDesc('id')->limit($perPage + 1)->get();

        $hasMore = $activities->count() > $perPage;
        $items = $activities->take($perPage)->values();

        // Step 4: resolve every FK label the page needs, in one batch (no N+1)
        $labels = $this->labelResolver->resolve($items);

        return new ActivityLogPage($items, $labels, $hasMore ? $this->cursorFor($items->last())->encode() : null);
    }

    /**
     * The unique (morph alias => subject ids) pairs for the root plus every
     * model reachable through the declared dot-path relations.
     *
     * @param  array<int, string>  $relationPaths
     * @return array<string, array<int, int>>
     */
    private function collectSubjectIds(Model $root, array $relationPaths): array
    {
        $subjects = [[$root->getMorphClass(), $root->getKey()]];

        foreach ($relationPaths as $path) {
            foreach ($this->resolvePath($root, $path) as $related) {
                $subjects[] = [$related->getMorphClass(), $related->getKey()];
            }
        }

        $byAlias = [];

        foreach ($subjects as [$alias, $id]) {
            $byAlias[$alias][$id] = $id; // dedupe by id
        }

        return array_map('array_values', $byAlias);
    }

    /**
     * Walk a dot-path relation from an already eager-loaded model, returning
     * every related instance found. Reads only already-loaded relations
     * (getRelation), never triggers a query — the caller is responsible for
     * eager-loading every declared path upfront.
     *
     * @return array<int, Model>
     */
    private function resolvePath(Model $root, string $path): array
    {
        $current = [$root];

        foreach (explode('.', $path) as $segment) {
            $next = [];

            foreach ($current as $model) {
                $related = $model->getRelation($segment);

                if ($related instanceof Collection) {
                    $next = array_merge($next, $related->all());
                } elseif ($related instanceof Model) {
                    $next[] = $related;
                }
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * Activity rows whose subject is one of the collected pairs — grouped by
     * alias so each group's ids go through a single bound `whereIn`, never a
     * raw/interpolated value (security.md §8).
     *
     * @param  array<string, array<int, int>>  $subjectIdsByAlias
     * @return Builder<Activity>
     */
    private function subjectsQuery(array $subjectIdsByAlias): Builder
    {
        return Activity::query()->where(function (Builder $query) use ($subjectIdsByAlias): void {
            foreach ($subjectIdsByAlias as $alias => $ids) {
                $query->orWhere(function (Builder $group) use ($alias, $ids): void {
                    $group->where('subject_type', $alias)->whereIn('subject_id', $ids);
                });
            }
        });
    }

    /**
     * Narrows the feed to a single event. Filtering server-side (not on the
     * fetched page) is what keeps the keyset pages full and `next_cursor`
     * meaningful once a filter is active.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyEvent(Builder $query, ?string $event): void
    {
        if ($event === null) {
            return;
        }

        $query->where('event', $event);
    }

    /**
     * @param  Builder<Activity>  $query
     */
    private function applyCursor(Builder $query, ?ActivityLogCursor $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        $query->where(function (Builder $q) use ($cursor): void {
            $q->where('created_at', '<', $cursor->createdAt)
                ->orWhere(function (Builder $tie) use ($cursor): void {
                    $tie->where('created_at', $cursor->createdAt)->where('id', '<', $cursor->id);
                });
        });
    }

    private function cursorFor(Activity $activity): ActivityLogCursor
    {
        return new ActivityLogCursor($activity->getRawOriginal('created_at'), (int) $activity->id);
    }
}
