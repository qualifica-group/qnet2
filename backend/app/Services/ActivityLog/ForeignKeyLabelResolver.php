<?php

namespace App\Services\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Resolves human-readable labels for FK-shaped fields (`*_id`) across a whole
 * PAGE of aggregated activity-log entries (spec 0034 FK-label extension): one
 * label query per distinct related model class, never per row/field — no
 * N+1 regardless of how many entries/modules the page mixes.
 *
 * FK detection is fully generic, no per-module logic (backend.md §1): a field
 * `{prefix}_id` on a subject model is only treated as an FK when that model
 * exposes a `Str::camel({prefix})` BelongsTo relation; the related class then
 * comes straight from the relation, never guessed from the field name. A
 * `_id` field with no matching relation resolves to a null display, not an
 * error.
 *
 * The one exception is `activity-log.foreign_keys`: fields an EXPLICIT
 * `activity()` entry reports on a subject that has no relation for them
 * (request management logs Quote-level fields on the Opportunity, D-9). Such
 * a field may also carry a LIST of ids (`product_lines`, `manager_slots`),
 * whose labels ActivityLogEntryResource joins.
 */
final class ForeignKeyLabelResolver
{
    /** @var array<string, string|null> memo of `{subjectClass}:{field}` => related class, for this request */
    private array $relatedClassCache = [];

    public function __construct(private readonly ActivityLogLabelFetcher $labelFetcher) {}

    /**
     * @param  Collection<int, Activity>  $activities  one page, already fetched
     * @return array<string, array<string, array<int, string>>> [subject_type alias][field][id] => label
     */
    public function resolve(Collection $activities): array
    {
        // Step 1: which (subject alias, field) pairs point to which related class
        $relatedClassByAliasField = $this->detectForeignKeys($activities);

        // Step 2: every id referenced through those pairs, grouped by related class
        $idsByClass = $this->collectIds($activities, $relatedClassByAliasField);

        // Step 3: one label query per related class
        $labelsByClass = $this->labelFetcher->fetch($idsByClass);

        // Step 4: project back onto [alias][field][id] => label
        return $this->buildLabelMap($relatedClassByAliasField, $labelsByClass);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<string, array<string, class-string<Model>>> [alias][field] => related model class
     */
    private function detectForeignKeys(Collection $activities): array
    {
        $map = [];
        $subjectClassByAlias = [];

        foreach ($activities as $activity) {
            $alias = $activity->subject_type;

            // `logOnlyDirty()` means an `updated` row only carries its OWN
            // dirty fields while `created` carries every fillable one, so
            // every activity of this alias must be probed — not just the
            // first — to see the full set of `_id` fields the module can log.
            if (! array_key_exists($alias, $subjectClassByAlias)) {
                $subjectClassByAlias[$alias] = Relation::getMorphedModel($alias);
            }

            $subjectClass = $subjectClassByAlias[$alias];

            if ($subjectClass === null) {
                continue;
            }

            $map[$alias] = ($map[$alias] ?? []) + $this->foreignKeyFieldsOf($subjectClass, $activity);
        }

        return $map;
    }

    /**
     * @param  class-string<Model>  $subjectClass
     * @return array<string, class-string<Model>>
     */
    private function foreignKeyFieldsOf(string $subjectClass, Activity $activity): array
    {
        $fields = collect(($activity->properties ?? new Collection)->get('attributes', []))->keys();
        /** @var array<string, class-string<Model>> $explicit */
        $explicit = config("activity-log.foreign_keys.{$activity->subject_type}", []);
        $map = [];

        foreach ($fields as $field) {
            if (isset($explicit[$field])) {
                $map[$field] = $explicit[$field];

                continue;
            }

            if (! str_ends_with($field, '_id')) {
                continue;
            }

            $relatedClass = $this->relatedClassFor($subjectClass, $field);

            if ($relatedClass !== null) {
                $map[$field] = $relatedClass;
            }
        }

        return $map;
    }

    /**
     * @param  class-string<Model>  $subjectClass
     * @return class-string<Model>|null
     */
    private function relatedClassFor(string $subjectClass, string $field): ?string
    {
        $cacheKey = "{$subjectClass}:{$field}";

        if (array_key_exists($cacheKey, $this->relatedClassCache)) {
            return $this->relatedClassCache[$cacheKey];
        }

        $relationName = Str::camel(Str::beforeLast($field, '_id'));

        if (! method_exists($subjectClass, $relationName)) {
            return $this->relatedClassCache[$cacheKey] = null;
        }

        $relation = (new $subjectClass)->{$relationName}();

        return $this->relatedClassCache[$cacheKey] = $relation instanceof BelongsTo
            ? $relation->getRelated()::class
            : null;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  array<string, array<string, class-string<Model>>>  $relatedClassByAliasField
     * @return array<class-string<Model>, array<int, int>>
     */
    private function collectIds(Collection $activities, array $relatedClassByAliasField): array
    {
        $idsByClass = [];

        foreach ($activities as $activity) {
            $fields = $relatedClassByAliasField[$activity->subject_type] ?? [];

            if ($fields === []) {
                continue;
            }

            $properties = $activity->properties ?? new Collection;
            $attributes = collect($properties->get('attributes', []));
            $old = collect($properties->get('old', []));

            foreach ($fields as $field => $relatedClass) {
                foreach ([...(array) $attributes->get($field), ...(array) $old->get($field)] as $value) {
                    if (is_int($value) || is_numeric($value)) {
                        $idsByClass[$relatedClass][(int) $value] = (int) $value;
                    }
                }
            }
        }

        return array_map('array_values', $idsByClass);
    }

    /**
     * @param  array<string, array<string, class-string<Model>>>  $relatedClassByAliasField
     * @param  array<class-string<Model>, array<int, string>>  $labelsByClass
     * @return array<string, array<string, array<int, string>>>
     */
    private function buildLabelMap(array $relatedClassByAliasField, array $labelsByClass): array
    {
        $map = [];

        foreach ($relatedClassByAliasField as $alias => $fields) {
            foreach ($fields as $field => $relatedClass) {
                $map[$alias][$field] = $labelsByClass[$relatedClass] ?? [];
            }
        }

        return $map;
    }
}
