<?php

namespace App\Tables\CommissionConfigurations;

final class CommissionConfigurationColumnCatalog
{
    public static function columns(): array
    {
        return [
            self::column('name', 'text', searchable: true),
            self::column('recipient_role', 'badge'),
            self::column('application_scope', 'badge'),
            self::column('category', 'text'),
            self::column('product', 'text'),
            self::column('commission_type', 'badge'),
            self::column('value', 'number'),
            self::column('priority', 'number'),
            self::column('status', 'badge'),
            self::column('updated_at', 'datetime', filterType: 'date'),
            // Spec 0089 D-11: appended LAST — inserting it anywhere else
            // would shift every column layout already persisted by users.
            // Not sortable: the value is resolved across three different
            // recipient tables, with no single column to order by.
            self::column('recipient', 'text', filterType: 'set', sortable: false),
        ];
    }

    public static function filters(): array
    {
        return array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'] ?? 'text',
            ],
            self::columns(),
        );
    }

    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'commission-configurations.view'],
            ['key' => 'edit', 'label' => 'actions.edit', 'icon' => 'pencil', 'type' => 'link', 'confirm' => false, 'permission' => 'commission-configurations.update'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'commission-configurations.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'commission-configurations.viewActivity'],
        ];
    }

    private static function column(
        string $id,
        string $type,
        bool $searchable = false,
        ?string $filterType = null,
        bool $sortable = true,
    ): array {
        // `sortable`/`visible`/`filterable` are structural (ResolvesColumnConfig
        // reads `$column['sortable']` unconditionally) and must always be
        // present, even when false — only the truly optional keys go through
        // the drop-if-empty filter below.
        $optional = array_filter([
            'filterType' => $filterType ?? (in_array($id, ['category', 'product'], true)
                ? 'set'
                : ($type === 'number' ? 'number' : ($type === 'badge' ? 'set' : 'text'))),
            'searchable' => $searchable,
        ], static fn (mixed $value): bool => $value !== null && $value !== false);

        return [
            'id' => $id,
            'label' => "commissionConfigurations.columns.{$id}",
            'type' => $type,
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => true,
            ...$optional,
        ];
    }
}
