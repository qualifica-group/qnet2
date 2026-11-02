<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > tasks: the pick-lists of that area (D-2).
return [
    'key' => 'config-tasks',
    'label' => 'navigation.tasks',
    'icon' => 'clipboard-list',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'task-statuses',
            'label' => 'navigation.taskStatuses',
            'icon' => 'circle-dot',
            'route' => '/task-statuses',
            'permission' => 'task-statuses.view',
        ],
        [
            'key' => 'task-types',
            'label' => 'navigation.taskTypes',
            'icon' => 'layers',
            'route' => '/task-types',
            'permission' => 'task-types.view',
        ],
        [
            'key' => 'task-categories',
            'label' => 'navigation.taskCategories',
            'icon' => 'folder',
            'route' => '/task-categories',
            'permission' => 'task-categories.view',
        ],
        [
            'key' => 'support-topics',
            'label' => 'navigation.supportTopics',
            'icon' => 'life-buoy',
            'route' => '/support-topics',
            'permission' => 'support-topics.view',
        ],
        [
            'key' => 'task-priorities',
            'label' => 'navigation.taskPriorities',
            'icon' => 'flag',
            'route' => '/task-priorities',
            'permission' => 'task-priorities.view',
        ],
        [
            'key' => 'task-importances',
            'label' => 'navigation.taskImportances',
            'icon' => 'star',
            'route' => '/task-importances',
            'permission' => 'task-importances.view',
        ],
        [
            'key' => 'task-templates',
            'label' => 'navigation.taskTemplates',
            'icon' => 'layout-template',
            'route' => '/task-templates',
            'permission' => 'task-templates.view',
        ],
    ],
];
