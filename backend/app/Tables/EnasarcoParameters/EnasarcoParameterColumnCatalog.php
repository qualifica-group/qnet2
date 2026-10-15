<?php

namespace App\Tables\EnasarcoParameters;

use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue of the `enasarco-parameters`
 * table (spec 0213): every column is a real column, handled by the generic
 * engine. Pure data.
 */
final class EnasarcoParameterColumnCatalog
{
    public const string LABEL_PREFIX = 'enasarcoParameters.columns.';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            self::column('year', 'number', filter: 'number'),
            self::column('total_rate', 'number', filter: 'number'),
            self::column('agent_share_percent', 'number', filter: 'number'),
            self::column('single_firm_ceiling', 'number', filter: 'number'),
            self::column('multi_firm_ceiling', 'number', filter: 'number'),
            self::column('updated_at', 'date'),
        ], [
            // Spec 0264, D-3: the notes stay on the record page.
            'year' => [],
            'total_rate' => [],
            'agent_share_percent' => [],
            'single_firm_ceiling' => [],
            'multi_firm_ceiling' => [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filters = [];

        foreach (self::columns() as $column) {
            if ($column['filterable']) {
                $filters[] = ['columnId' => $column['id'], 'type' => $column['filterType']];
            }
        }

        return $filters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'enasarco-parameters.view'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'enasarco-parameters.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'enasarco-parameters.viewActivity'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, ?string $filter = null): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => $type,
            'visible' => true,
            'sortable' => true,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        return $column;
    }
}
