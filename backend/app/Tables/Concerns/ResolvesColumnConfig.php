<?php

namespace App\Tables\Concerns;

use App\Models\User;

/**
 * Per-column resolution for GET /columns: the declarative→client-facing
 * column shape (resolveColumn), its dynamic-option/badge/enumKey hooks, and
 * the `hasFilterValues` derivation. Split out of AbstractTableDefinition to
 * stay within the file-size budget (engineering.md §6) — this is the WHOLE
 * of "how one column declaration becomes one column in the config", a single
 * cohesive concern also touched by spec 0054 (relation-editing `editor`/
 * `relation` keys).
 *
 * The using class must implement TableDefinition; `editableColumnIds()`
 * comes from the sibling ResolvesEditableColumns trait.
 */
trait ResolvesColumnConfig
{
    /**
     * Dynamic options for a column/filter id (e.g. `roles` resolved per actor).
     * Concrete definitions override for columns whose options are not static.
     * Return null to keep the statically-declared `options` (if any).
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return null;
    }

    /**
     * Per-value badge metadata for a `badge` column (label/color/icon resolved
     * from a domain enum via App\Enums\Concerns\HasMeta::options()). Each entry
     * is an EnumMeta::toArray() shape. Return null to omit the `badges` key.
     *
     * The frontend renders the badge purely from this metadata, so it never has
     * to know the domain enum: values, labels, colors and icons all come from
     * the backend.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return null;
    }

    /**
     * The snake_case domain-enum key (config/config.php → form_enums) a `badge`
     * column maps to, e.g. `user_type` → `personal_data_type`. The frontend uses
     * it to localize the badge label from its own i18n resources
     * (`enums.<enumKey>.<value>`) instead of the backend-supplied label. Return
     * null to omit the `enumKey` key (and keep the backend label authoritative).
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return null;
    }

    /**
     * Resolve a single column declaration into its client-facing shape.
     *
     * Presentation properties (visible/width/order) come from the layout and are
     * user-overridable (ADR-0004); structural/security properties (sortable/
     * filterable/filterType/hasFilterValues) come straight from the declaration
     * and are never user-overridable. `filterType` drives the frontend filter
     * widget (a set filter can sit on a text/badge-rendered column, e.g. the geo
     * columns), so it is part of the public contract. `hasFilterValues` (spec
     * 0004/0005) tells the frontend whether the column's Set Filter can
     * enumerate a discrete value list at all — false for COMPUTED columns with
     * no discrete list (a formatted address string, an aggregate count), which
     * also have no real DB column to `SELECT DISTINCT` on. `badges` is emitted
     * only for `badge` columns, keeping every other column byte-identical to
     * before.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, array{visible: bool, width: int|null, order: int}>  $layout
     * @param  array<int, string>  $editableIds
     * @return array<string, mixed>
     */
    private function resolveColumn(array $column, User $actor, array $layout, array $editableIds): array
    {
        $resolved = [
            'id' => $column['id'],
            'label' => $column['label'],
            'type' => $column['type'],
            // Presentation properties a user may override (ADR-0004).
            'visible' => $layout[$column['id']]['visible'],
            'width' => $layout[$column['id']]['width'],
            'order' => $layout[$column['id']]['order'],
            // Structural / security properties — NEVER user-overridable.
            'sortable' => $column['sortable'],
            'filterable' => $column['filterable'],
            'filterType' => $column['filterType'] ?? null,
            'hasFilterValues' => $this->hasFilterValues($column),
            // Inline cell-editing (spec 0053, D-2): already reduced for the
            // actor — a UI hint, never the authority (the PATCH endpoint
            // re-derives its own guards against the real row).
            'editable' => in_array($column['id'], $editableIds, true),
            'options' => $this->optionsFor($column['id'], $actor)
                ?? ($column['options'] ?? null),
        ];

        return $this->withOptionalColumnExtras($resolved, $column, $actor);
    }

    /**
     * Conditionally-emitted column extras: `badges`/`enumKey` (badge
     * columns), `editor`/`relation` (spec 0054 relation-editing). Each is
     * added only when applicable, so every other column stays byte-identical
     * to before.
     *
     * @param  array<string, mixed>  $resolved
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private function withOptionalColumnExtras(array $resolved, array $column, User $actor): array
    {
        $badges = $this->badgesFor($column['id'], $actor);

        if ($badges !== null) {
            $resolved['badges'] = $badges;
        }

        $enumKey = $this->enumKeyFor($column['id'], $actor);

        if ($enumKey !== null) {
            $resolved['enumKey'] = $enumKey;
        }

        // Spec 0055, D-1: `editor` is a first-class catalogue key — the editor
        // is a DOMAIN choice (a working-state is a select even though its
        // rendering `type` is `text`), not a consequence of the rendering
        // type. A declared `relation` still implies `editor: 'relation'`, so
        // every spec 0054 column stays byte-identical without declaring it.
        $editor = $column['editor'] ?? (isset($column['relation']) ? 'relation' : null);

        if ($editor !== null) {
            $resolved['editor'] = $editor;
        }

        if (isset($column['relation'])) {
            $resolved['relation'] = ['resource' => $column['relation']['resource']];

            // ROW-SCOPED picker params: `['<for-select param>' => '<column
            // id>']` — the editor reads that column's value off the row being
            // edited and sends it as a `/for-select` param, so the dropdown
            // offers only the values valid FOR THAT ROW (e.g. the users of
            // the row's own operational site). Emitted verbatim; a row whose
            // scope column is empty simply sends no param (unfiltered list),
            // exactly like the form pickers. This is a UI narrowing only —
            // the write path still re-validates through
            // RelationValueScopeChecker, which is unaware of it by design.
            if (isset($column['relation']['scope'])) {
                $resolved['relation']['scope'] = $column['relation']['scope'];
            }

            // Spec 0075, D-4: the scope above is a DEFAULT the editor may let
            // the operator lift; `lockScope` says this domain refuses what is
            // outside it, so the escape must not be offered at all. Emitted
            // only when declared, so every other relation column stays
            // byte-identical.
            if (($column['relation']['lockScope'] ?? false) === true) {
                $resolved['relation']['lockScope'] = true;
            }
        }

        return $resolved;
    }

    /**
     * Whether the Set Filter (spec 0004/0005) can enumerate a discrete value
     * list for this column. Explicit `hasFilterValues` in the raw declaration
     * wins (COMPUTED columns with no discrete list — e.g. a formatted address
     * string, an aggregate count — declare it `false`); otherwise defaults to
     * true whenever the column is filterable with a declared filterType.
     *
     * @param  array<string, mixed>  $column
     */
    private function hasFilterValues(array $column): bool
    {
        if (array_key_exists('hasFilterValues', $column)) {
            return (bool) $column['hasFilterValues'];
        }

        return ($column['filterable'] ?? false) === true && ($column['filterType'] ?? null) !== null;
    }
}
