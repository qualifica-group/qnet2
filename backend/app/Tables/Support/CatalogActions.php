<?php

namespace App\Tables\Support;

/**
 * The view/delete/activity row actions shared by the plain catalogue grids
 * (spec 0271): pure data, one permission prefix per resource.
 */
final class CatalogActions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(string $resource): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => "{$resource}.view"],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => "{$resource}.delete"],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => "{$resource}.viewActivity"],
        ];
    }
}
