<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > commercial: the pick-lists of that area (D-2).
return [
    'key' => 'config-commercial',
    'label' => 'navigation.commercial',
    'icon' => 'handshake',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'pipeline-statuses',
            'label' => 'navigation.pipelineStatuses',
            'icon' => 'tag',
            'route' => '/pipeline-statuses',
            'permission' => 'pipeline-statuses.view',
        ],
        [
            'key' => 'quote-workflows',
            'label' => 'navigation.quoteWorkflows',
            'icon' => 'workflow',
            'route' => '/quote-workflows',
            'permission' => 'quote-workflows.viewAny',
        ],
        [
            'key' => 'work-order-workflows',
            'label' => 'navigation.workOrderWorkflows',
            'icon' => 'workflow',
            'route' => '/work-order-workflows',
            'permission' => 'work-order-workflows.viewAny',
        ],
        [
            'key' => 'contract-statuses',
            'label' => 'navigation.contractStatuses',
            'icon' => 'tag',
            'route' => '/contract-statuses',
            'permission' => 'contract-statuses.view',
        ],
        [
            'key' => 'referent-types',
            'label' => 'navigation.referentTypes',
            'icon' => 'tags',
            'route' => '/referent-types',
            'permission' => 'referent-types.view',
        ],
        [
            'key' => 'sources',
            'label' => 'navigation.sources',
            'icon' => 'waypoints',
            'route' => '/sources',
            'permission' => 'sources.view',
        ],
        [
            'key' => 'sectors',
            'label' => 'navigation.sectors',
            'icon' => 'list-tree',
            'route' => '/sectors',
            'permission' => 'sectors.view',
        ],
        [
            'key' => 'tags',
            'label' => 'navigation.tags',
            'icon' => 'tag',
            'route' => '/tags',
            'permission' => 'tags.view',
        ],
        [
            'key' => 'reward-types',
            'label' => 'navigation.rewardTypes',
            'icon' => 'gift',
            'route' => '/reward-types',
            'permission' => 'reward-types.view',
        ],
        [
            'key' => 'reward-statuses',
            'label' => 'navigation.rewardStatuses',
            'icon' => 'list-checks',
            'route' => '/reward-statuses',
            'permission' => 'reward-statuses.view',
        ],
    ],
];
