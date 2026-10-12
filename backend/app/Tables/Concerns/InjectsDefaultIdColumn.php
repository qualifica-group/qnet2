<?php

namespace App\Tables\Concerns;

use App\Authorization\LegacyIdVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Injects a default hidden `id` column into every table that does not declare
 * its own, so the primary key is uniformly surfaceable across the grid, its
 * column-visibility preferences and the sort/filter whitelists — with no
 * per-domain declaration. When the model's table carries the legacy `old_id`
 * (the external-migration anchor) and the authenticated actor may see it
 * (LegacyIdVisibility), a hidden `old_id` column follows it.
 *
 * Both columns are hidden by default (ADR-0004 visibility): the row payload
 * already carries `id` (every mapRow returns it) and `old_id` (TableService
 * attaches it for the same actors), so the frontend can toggle them on
 * demand. A definition that needs different semantics (e.g. the roles table,
 * which keeps `id` non-filterable) declares its own column, which wins — this
 * is a default, not a mandate. The raw `columns()` catalogue (used by
 * export/migration) is left untouched: the defaults are a grid/preferences
 * concern only.
 */
trait InjectsDefaultIdColumn
{
    private const LEGACY_ID_COLUMN = 'old_id';

    /**
     * Whether each model table carries `old_id`, keyed by connection + table:
     * columnsWithDefaultId() runs several times per request and the schema
     * never changes at runtime, so the lookup is paid once per process.
     *
     * @var array<string, bool>
     */
    private static array $legacyIdTables = [];

    /**
     * The declared columns with the default hidden `id` column prepended as the
     * FIRST column (unless the definition declares its own `id`), followed by
     * the default hidden `old_id` column when the model's table has one, the
     * definition does not declare it and the actor may see it.
     *
     * The actor is the authenticated user: every caller runs inside a table
     * request, and resolving it here keeps `old_id` out of the config, the
     * sort whitelist and the layout defaults alike for an actor without
     * `legacy-ids.view`.
     *
     * `id` is the leftmost column (once the user toggles it visible), matching
     * the tables that already declare their own `id` first (users, roles,
     * companies, …); `old_id` sits right after it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function columnsWithDefaultId(): array
    {
        $columns = $this->columns();
        $declaredIds = array_column($columns, 'id');

        if (! in_array('id', $declaredIds, true)) {
            array_unshift($columns, $this->defaultIdColumn());
        }

        if (! in_array(self::LEGACY_ID_COLUMN, $declaredIds, true)
            && LegacyIdVisibility::allows(Auth::user())
            && $this->hasLegacyIdColumn()) {
            $idPosition = (int) array_search('id', array_column($columns, 'id'), true);
            array_splice($columns, $idPosition + 1, 0, [$this->defaultLegacyIdColumn()]);
        }

        return $columns;
    }

    /**
     * The default hidden `id` column: the primary key as a sortable column,
     * hidden by default. Not filterable — a filter widget on a technical id is
     * clutter; a table that wants it filterable declares its own `id` column
     * (as users/companies do), which wins over this default.
     *
     * @return array<string, mixed>
     */
    private function defaultIdColumn(): array
    {
        return [
            'id' => 'id',
            'label' => 'table.columns.id',
            'type' => 'number',
            'visible' => false,
            'sortable' => true,
            'filterable' => false,
            'filterType' => null,
        ];
    }

    /**
     * The default hidden `old_id` column: the legacy id, sortable and hidden by
     * default, with the same non-filterable rationale as defaultIdColumn().
     *
     * @return array<string, mixed>
     */
    private function defaultLegacyIdColumn(): array
    {
        return [
            'id' => self::LEGACY_ID_COLUMN,
            'label' => 'table.columns.old_id',
            'type' => 'number',
            'visible' => false,
            'sortable' => true,
            'filterable' => false,
            'filterType' => null,
        ];
    }

    private function hasLegacyIdColumn(): bool
    {
        $modelClass = $this->modelClass();
        $model = new $modelClass;
        $key = $model->getConnectionName().'.'.$model->getTable();

        return self::$legacyIdTables[$key] ??= Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), self::LEGACY_ID_COLUMN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract public function columns(): array;

    /**
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;
}
