<?php

namespace App\Services;

use App\DataObjects\Table\ColumnState;
use App\Models\User;
use App\Models\UserTablePreference;
use App\Tables\TableDefinition;

/**
 * Per-user table column preferences: merge (read), sparse diff (write), reset
 * (ADR-0004 / docs/api/0003-table-preferences.md).
 *
 * Domain-agnostic: it operates ONLY through the resolved TableDefinition and the
 * generic user_table_preferences store, so every table inherits persistence with
 * no per-domain code. The PHP definition stays the single source of truth — the
 * stored value is a SPARSE delta (only the properties that deviate from the
 * default), computed here on the server so it can never go stale when a PHP
 * default changes.
 *
 * Only PRESENTATION properties are ever read from / written to the delta
 * (visible / width / order). Structural / security properties
 * (sortable / filterable / type / options) always come from the definition, so a
 * user can never widen the SSRM whitelist through their preferences.
 */
class TablePreferenceService
{
    /** Properties a user is allowed to override. Nothing else is honoured. */
    private const array OVERRIDABLE = ['visible', 'width', 'order'];

    /**
     * Merge the actor's saved delta into a resolved (default) config and return
     * the personalized config. Columns are re-ordered by the effective `order`.
     *
     * Merge rules (ADR-0004):
     *  - column in definition, absent from delta → default kept;
     *  - column in both → delta overrides visible/width/order only;
     *  - delta entry for a column no longer in the definition → ignored
     *    (renamed/removed in PHP — no error, no stale data);
     *  - unrecognized property in the delta → ignored.
     *
     * @param  array<string, mixed>  $config  the definition's resolveConfig() output
     * @return array<string, mixed>
     */
    public function applyTo(array $config, TableDefinition $definition, User $actor): array
    {
        $delta = $this->deltaFor($definition, $actor);

        // Tells the frontend whether the user has a saved layout, so it can offer
        // "reset to default" only when there is actually something to reset.
        $config['customized'] = $delta !== [];

        if ($delta === []) {
            return $config;
        }

        /** @var array<int, array<string, mixed>> $columns */
        $columns = $config['columns'] ?? [];

        $config['columns'] = $this->sortByOrder(array_map(
            function (array $column) use ($delta): array {
                $override = $delta[$column['id']] ?? null;

                if (is_array($override)) {
                    foreach (self::OVERRIDABLE as $key) {
                        if (array_key_exists($key, $override)) {
                            $column[$key] = $override[$key];
                        }
                    }
                }

                return $column;
            },
            $columns,
        ));

        return $config;
    }

    /**
     * Persist the actor's current column state as a SPARSE delta and return the
     * stored delta. The frontend sends the full state of the columns it shows;
     * we diff it against the definition default and keep only deviations.
     * Idempotent upsert on (user_id, domain). Columns unknown to the definition
     * are ignored defensively (the FormRequest already 422s them).
     *
     * A submitted column replaces its stored override entirely; a column the
     * client did not submit keeps its stored override. The grid can show only
     * part of the domain's columns (request-management's category tabs each
     * carry their own `attr.*` set), so a save from one view must not discard
     * what another view customized.
     *
     * @param  array<int, ColumnState>  $columnsState
     * @return array<string, array<string, mixed>>
     */
    public function save(TableDefinition $definition, User $actor, array $columnsState): array
    {
        $defaults = $definition->defaultColumnLayout();
        $delta = $this->deltaFor($definition, $actor);

        foreach ($columnsState as $column) {
            if (! array_key_exists($column->id, $defaults)) {
                continue; // unknown column — ignore (defence in depth)
            }

            $default = $defaults[$column->id];
            $diff = [];

            foreach ($this->submittedOverrides($column) as $key => $value) {
                if ($value !== $default[$key]) {
                    $diff[$key] = $value;
                }
            }

            unset($delta[$column->id]);

            if ($diff !== []) {
                $delta[$column->id] = $diff;
            }
        }

        // Stored overrides of columns no longer defined are dropped, not carried on.
        $delta = array_intersect_key($delta, $defaults);

        UserTablePreference::query()->updateOrCreate(
            ['user_id' => $actor->id, 'domain' => $definition->domain()],
            ['preferences' => $delta],
        );

        return $delta;
    }

    /**
     * The presentation overrides the user actually submitted for a column, keyed
     * by their delta property name. Properties the user did not submit (null on
     * the DTO) are omitted, so only real deviations can become part of the delta.
     *
     * @return array<string, bool|int>
     */
    private function submittedOverrides(ColumnState $column): array
    {
        $overrides = [];

        if ($column->visible !== null) {
            $overrides['visible'] = $column->visible;
        }

        if ($column->width !== null) {
            $overrides['width'] = $column->width;
        }

        if ($column->order !== null) {
            $overrides['order'] = $column->order;
        }

        return $overrides;
    }

    /**
     * Reset the actor's layout for this table to the PHP default by removing the
     * saved row. Explicit user action only — preferences are never auto-deleted.
     */
    public function reset(TableDefinition $definition, User $actor): void
    {
        UserTablePreference::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->delete();
    }

    /**
     * The actor's stored sparse delta for this domain (empty when none).
     *
     * @return array<string, array<string, mixed>>
     */
    private function deltaFor(TableDefinition $definition, User $actor): array
    {
        $preference = UserTablePreference::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->first();

        $delta = $preference?->preferences ?? [];

        return is_array($delta) ? $delta : [];
    }

    /**
     * Sort columns by their effective `order` (stable for equal orders).
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function sortByOrder(array $columns): array
    {
        usort(
            $columns,
            static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0),
        );

        return $columns;
    }
}
