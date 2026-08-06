<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves a `relation`-type custom field's stored id(s) to display label(s)
 * for the Table grid (spec 0021, AC-015: relations hydrated to a label with
 * no N+1). NOT used by the detail/read API (`data.custom_fields`), which
 * keeps raw ids per the data_contract.
 *
 * Label source: the target model's own "display attribute", picked from a
 * short priority list of common naming columns. Reusing the target's actual
 * ForSelectResource would need a NEW entity_type→resource class registry the
 * framework does not have yet; this heuristic stays generic (works for any
 * custom-fieldable target, present or future) without that extra
 * abstraction — documented trade-off, not an oversight.
 *
 * Batches by target model class: every id is looked up via one explicit
 * `whereIn` query (never a lazy-loaded relation, so `preventLazyLoading`
 * never trips) and memoized per (model class, id) for the lifetime of this
 * instance. `CustomFieldAwareTableDefinition` holds ONE instance across every
 * row of a single `TableService::rows()` call, so a repeated id — or every id
 * of a `many`-cardinality field's own array in one row — is never re-queried.
 */
class CustomFieldRelationLabelResolver
{
    /** @var array<int, string> */
    private const array DISPLAY_ATTRIBUTE_CANDIDATES = ['denomination', 'name', 'label', 'title'];

    /** @var array<class-string<Model>, array<int, string>> */
    private array $labelCache = [];

    /** @var array<class-string<Model>, string> */
    private array $displayColumnCache = [];

    public function __construct(private readonly CustomFieldEntityRegistry $entityRegistry) {}

    /**
     * A `many`-cardinality field's labels are joined into a single string:
     * the column's declared `columnType()` is `text` (a scalar), so the row
     * value stays consistent with that contract regardless of cardinality.
     */
    public function resolve(CustomFieldDefinition $definition, mixed $stored): ?string
    {
        $modelClass = $this->targetModelClass($definition);

        if ($modelClass === null || $stored === null) {
            return null;
        }

        $ids = $this->normalizeIds($stored);

        if ($ids === []) {
            return null;
        }

        $this->warm($modelClass, $ids);

        $labels = array_values(array_filter(array_map(
            fn (int $id): ?string => $this->labelCache[$modelClass][$id] ?? null,
            $ids,
        )));

        return $labels === [] ? null : implode(', ', $labels);
    }

    /**
     * The same display-column heuristic resolve() uses internally, exposed
     * for a consumer that already knows the target model class (spec 0047
     * amendment 2026-07-27: App\Support\QuoteWorkflows\
     * QuoteCriterionFieldRegistry needs a custom relation field's label
     * column to build a criterion's value_label, e.g.
     * `companies.denomination`) — same per-model-class cache, same candidate
     * list, no behavior change.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function displayColumnFor(string $modelClass): string
    {
        return $this->displayColumn($modelClass, new $modelClass);
    }

    /**
     * @return class-string<Model>|null
     */
    private function targetModelClass(CustomFieldDefinition $definition): ?string
    {
        $entityType = $definition->relation_target['entity_type'] ?? null;

        return is_string($entityType) ? $this->entityRegistry->modelClassFor($entityType) : null;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(mixed $stored): array
    {
        $raw = is_array($stored) ? $stored : [$stored];

        return array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter($raw, static fn (mixed $id): bool => is_scalar($id)),
        )));
    }

    /**
     * Fetch every id not already cached for `$modelClass`, one bound
     * `whereIn` query for the whole missing set.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     */
    private function warm(string $modelClass, array $ids): void
    {
        $cached = array_keys($this->labelCache[$modelClass] ?? []);
        $missing = array_values(array_diff($ids, $cached));

        if ($missing === []) {
            return;
        }

        /** @var Model $instance */
        $instance = new $modelClass;
        $column = $this->displayColumn($modelClass, $instance);
        $key = $instance->getKeyName();

        foreach ($modelClass::query()->whereIn($key, $missing)->get([$key, $column]) as $row) {
            /** @var Model $row */
            $this->labelCache[$modelClass][(int) $row->getKey()] = (string) $row->getAttribute($column);
        }
    }

    /**
     * One `getColumnListing()` metadata lookup per target model class
     * (cached thereafter), never one `hasColumn()` round trip per candidate
     * — keeps the constant, per-model-class cost of picking a display
     * column from scaling with the candidate list length.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function displayColumn(string $modelClass, Model $instance): string
    {
        if (isset($this->displayColumnCache[$modelClass])) {
            return $this->displayColumnCache[$modelClass];
        }

        $columns = Schema::getColumnListing($instance->getTable());
        $column = $instance->getKeyName();

        foreach (self::DISPLAY_ATTRIBUTE_CANDIDATES as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $column = $candidate;

                break;
            }
        }

        return $this->displayColumnCache[$modelClass] = $column;
    }
}
