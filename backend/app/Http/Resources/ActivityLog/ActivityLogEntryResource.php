<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Wire shape for one aggregated activity-log entry (spec 0034, FK-label
 * extension). Wraps the stock Spatie `Activity` model — never exposed raw.
 * `subject_type` already IS the module alias (Relation::enforceMorphMap), so
 * `module` is a straight passthrough.
 *
 * @mixin Activity
 */
class ActivityLogEntryResource extends JsonResource
{
    /**
     * @param  Activity  $resource
     * @param  array<string, array<string, array<int, string>>>  $labels  [subject_type alias][field][id] => label, resolved for the whole page (see ForeignKeyLabelResolver)
     */
    public function __construct(
        $resource,
        private readonly array $labels = [],
        /** @var array<string, array<int, string>> */
        private readonly array $hiddenFields = [],
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'logged_at' => $activity->created_at?->toIso8601String(),
            'event' => $activity->event,
            'module' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer' => [
                'id' => $activity->causer?->id,
                'name' => $activity->causer?->name,
            ],
            'changes' => $this->changes($activity),
        ];
    }

    /**
     * Derive changes[] from Spatie's `properties` payload: `created`/
     * `restored` carry only `attributes` (old_value=null, mirrors the
     * "nothing existed before" semantics — AC-008); `updated` pairs
     * `attributes` with `old` (AC-007); `deleted` carries only `attributes` as
     * the final state, so it is exposed as old_value with new_value=null (the
     * field no longer exists after the row is gone). A change where BOTH
     * sides are null is noise, not a change (a field left null at creation,
     * or already null on a deleted row), so it is dropped rather than emitted
     * (spec 0034 FK-label extension); the entry itself still stands even if
     * that empties changes[] entirely.
     *
     * @return array<int, array{field: string, old_value: mixed, new_value: mixed, old_display: string|null, new_display: string|null}>
     */
    private function changes(Activity $activity): array
    {
        $properties = $activity->properties ?? new Collection;
        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        return $attributes->keys()
            ->map(fn (string $field): array => match ($activity->event) {
                'deleted' => ['field' => $field, 'old_value' => $attributes->get($field), 'new_value' => null],
                'updated' => ['field' => $field, 'old_value' => $old->get($field), 'new_value' => $attributes->get($field)],
                default => ['field' => $field, 'old_value' => null, 'new_value' => $attributes->get($field)],
            })
            ->reject(fn (array $change): bool => $change['old_value'] === null && $change['new_value'] === null)
            ->reject(fn (array $change): bool => in_array(
                $change['field'],
                $this->hiddenFields[$activity->subject_type] ?? [],
                true,
            ))
            ->map(fn (array $change): array => [...$change, ...$this->display($activity->subject_type, $change)])
            ->values()
            ->all();
    }

    /**
     * FK display labels for one change, resolved from the page-level label
     * map (see ForeignKeyLabelResolver): both null when `field` isn't a
     * resolved FK, or when the referenced id has no match (e.g. a record
     * deleted since — the frontend falls back to the raw id).
     *
     * @param  array{field: string, old_value: mixed, new_value: mixed}  $change
     * @return array{old_display: string|null, new_display: string|null}
     */
    private function display(string $subjectType, array $change): array
    {
        $labels = $this->labels[$subjectType][$change['field']] ?? null;

        if ($labels === null) {
            return ['old_display' => null, 'new_display' => null];
        }

        return [
            'old_display' => $this->labelFor($labels, $change['old_value']),
            'new_display' => $this->labelFor($labels, $change['new_value']),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function labelFor(array $labels, mixed $value): ?string
    {
        return $value === null ? null : ($labels[(int) $value] ?? null);
    }
}
