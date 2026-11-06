<?php

// Spec 0365 (menu on the legacy QNet "Statistiche" area). "Statistiche": read-only reports and
// dashboards over the sales and request data. Request statistics and enrollee management moved
// here from Commerciale with key, label, icon, route and permission unchanged.
return [
    'key' => 'statistics',
    'label' => 'navigation.statistics',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'agent-sales-report',
            'label' => 'navigation.agentSalesReport',
            'icon' => 'trending-up',
            'route' => '/agent-sales-report',
            'permission' => 'agent-sales-report.view',
        ],
        [
            'key' => 'request-statistics',
            'label' => 'navigation.requestStatistics',
            'icon' => 'chart-column',
            'route' => '/request-statistics',
            'permission' => 'request-statistics.view',
        ],
        [
            'key' => 'enrollee-management',
            'label' => 'navigation.enrolleeManagement',
            'icon' => 'user-plus',
            'route' => '/enrollee-management',
            'permission' => 'enrollee-management.view',
        ],
    ],
];
