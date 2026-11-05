<?php

namespace App\Tables\TreasuryMovements;

use App\Enums\TreasuryMovementType;

/**
 * Declarative column/filter/action catalogue of the `treasury-movements` domain
 * (spec 0360). Pure data. `movement_date`, `type` and `description` are real
 * columns; the relation columns are derived (TreasuryMovementRelationColumns) and
 * `inflow`/`outflow`/`sub_account` are computed per row, never sorted or filtered.
 */
final class TreasuryMovementColumnCatalog
{
    private const string LABELS = 'treasuryMovements.columns';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [...self::base('movement_date', 'date'), 'sortable' => true, 'filterable' => true, 'filterType' => 'date'],
            [...self::base('type', 'badge'), 'sortable' => true, 'filterable' => true, 'filterType' => 'set', 'options' => TreasuryMovementType::values()],
            [...self::base('account', 'text'), 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            [...self::base('counterpart_account', 'text'), 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            [...self::base('company', 'text'), 'visible' => false, 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            [...self::base('description', 'text'), 'sortable' => true, 'filterable' => true, 'filterType' => 'text', 'searchable' => true],
            self::base('sub_account', 'text'),
            [...self::base('registry', 'text'), 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            [...self::base('work_order', 'text'), 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            self::base('inflow', 'number'),
            self::base('outflow', 'number'),
            [...self::base('created_by', 'text'), 'visible' => false, 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filters = [];

        foreach (self::columns() as $column) {
            if (! $column['filterable']) {
                continue;
            }

            $filters[] = array_filter([
                'columnId' => $column['id'],
                'type' => $column['filterType'],
                'options' => $column['options'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $filters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'treasury-movements.view'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'treasury-movements.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'treasury-movements.viewActivity'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function base(string $id, string $type): array
    {
        return [
            'id' => $id,
            'label' => self::LABELS.'.'.$id,
            'type' => $type,
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }
}
