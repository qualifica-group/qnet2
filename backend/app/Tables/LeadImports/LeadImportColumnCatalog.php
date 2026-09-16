<?php

namespace App\Tables\LeadImports;

use App\Enums\ImportStatus;

/**
 * Declarative column/filter/action catalogue for the `lead-imports` domain:
 * the read-only history of every lead import run, rendered by the generic
 * table engine instead of a bespoke HTML table. Pure data (no logic). Every
 * column is a real `import_runs` column except the derived `user` (the
 * operator who started the run, resolved by ImportRunUserColumn).
 */
final class LeadImportColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'created_at',
                'label' => 'leadImports.columns.date',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // Derived: the operator who started the run (user decision
                // 2026-09-16). Sits right after the date, where "who/when"
                // read together; saved layouts are keyed by column id, so only
                // default layouts move.
                'id' => 'user',
                'label' => 'leadImports.columns.operator',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'original_filename',
                'label' => 'leadImports.columns.file',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'total_rows',
                'label' => 'leadImports.columns.records',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'imported_rows',
                'label' => 'leadImports.columns.imported',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Real `invalid_rows` column (the wizard's error count), labelled
                // "Errors" for the UI. Kept as the real column id — not an alias —
                // so the generic ORDER BY/WHERE whitelist targets a real column
                // with no derived-sort/filter hook.
                'id' => 'invalid_rows',
                'label' => 'leadImports.columns.errors',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'status',
                'label' => 'leadImports.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => array_map(
                    static fn (ImportStatus $case): string => $case->value,
                    ImportStatus::cases(),
                ),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'user', 'type' => 'set'],
            ['columnId' => 'original_filename', 'type' => 'text'],
            ['columnId' => 'total_rows', 'type' => 'number'],
            ['columnId' => 'imported_rows', 'type' => 'number'],
            ['columnId' => 'invalid_rows', 'type' => 'number'],
            [
                'columnId' => 'status',
                'type' => 'set',
                'options' => array_map(
                    static fn (ImportStatus $case): string => $case->value,
                    ImportStatus::cases(),
                ),
            ],
        ];
    }

    /**
     * Row actions, mirroring the CRUD tables' flow: `view` reopens the run in
     * the import wizard (the frontend adapter navigates to
     * `/leads/import?runId={id}`), `delete` removes the run through the generic
     * bulk-delete engine. Both gated by the lead module's `leads.import`
     * ability (the former `import-runs.*` set was removed 2026-07-17); `delete`
     * is additionally per-row gated by ImportRunPolicy in actionsFor().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'leadImports.actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'leads.import',
            ],
            [
                'key' => 'delete',
                'label' => 'leadImports.actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'leads.import',
            ],
        ];
    }
}
