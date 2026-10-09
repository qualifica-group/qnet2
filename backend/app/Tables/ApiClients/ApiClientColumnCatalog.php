<?php

namespace App\Tables\ApiClients;

/**
 * Declarative column/filter/action catalogue for the `api-clients` domain
 * (spec 0209). Pure data. `key_last_four`, `last_used_at` and
 * `created_by` are display-only: derived values, neither filterable
 * nor (except last_used_at, sorted by the definition) sortable.
 */
final class ApiClientColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'apiClients.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'name',
                'label' => 'apiClients.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'is_active',
                'label' => 'apiClients.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'expires_at',
                'label' => 'apiClients.columns.expires_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'key_last_four',
                'label' => 'apiClients.columns.key_last_four',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'last_used_at',
                'label' => 'apiClients.columns.last_used_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'created_by',
                'label' => 'apiClients.columns.created_by',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'created_at',
                'label' => 'apiClients.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'expires_at', 'type' => 'date'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'api-clients.view'],
            ['key' => 'edit', 'label' => 'actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'api-clients.update'],
            ['key' => 'rotate-key', 'label' => 'apiClients.actions.rotate_key', 'icon' => 'key-round', 'type' => 'action', 'confirm' => true, 'permission' => 'api-clients.update'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'api-clients.delete'],
        ];
    }
}
