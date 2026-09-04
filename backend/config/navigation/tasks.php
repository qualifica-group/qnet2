<?php

// "Task" (spec 0101) promoted to its own top-level collapsible group (user
// decision 2026-09-04), on the model of "Prodotti" and "Premi e Incentivi":
// the operational module and the five pick-lists that govern it live together,
// because they are one domain and are administered together.
//
// It is a group of its own rather than a child of an existing one because a
// Task belongs to no single domain: it attaches to FOUR different records —
// Anagrafica, Referente, Opportunita' and Commessa (D-1) — so filing it under
// any one of their groups would misstate its reach.
//
// The five configurators moved here OUT of "Configurazione" (same move
// `reward-statuses` made in 2026-07-27): a pick-list belongs beside the module
// it configures, not in a drawer of unrelated lookups.
//
// Route-less parent: renders collapsible and is dropped entirely when the
// actor can see none of its children.
return [
    'key' => 'tasks-group',
    'label' => 'navigation.tasks',
    'icon' => 'clipboard-list',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            // The Task module itself: the only operational entity here, the
            // five below configure it.
            'key' => 'tasks',
            'label' => 'navigation.tasks',
            'icon' => 'list-checks',
            'route' => '/tasks',
            'permission' => 'tasks.view',
        ],
        [
            // Task statuses (D-5): the only configurator carrying a
            // `system_key`, and the source of a Task's completion percentage.
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
    ],
];
