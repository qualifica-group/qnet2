<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Commerciale": the sales pipeline, from leads
// to quotes, requests and referral rewards. Its pick-lists are in Configurazione > Commerciale.
return [
    'key' => 'commercial',
    'label' => 'navigation.commercial',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'marketing-leads',
            'label' => 'navigation.marketingLeads',
            'icon' => 'megaphone',
            'route' => null,
            'permission' => null,
            'children' => [
                [
                    'key' => 'projects',
                    'label' => 'navigation.projects',
                    'icon' => 'layers',
                    'route' => '/projects',
                    'permission' => 'projects.view',
                ],
                [
                    'key' => 'campaigns',
                    'label' => 'navigation.campaigns',
                    'icon' => 'waypoints',
                    'route' => '/campaigns',
                    'permission' => 'campaigns.view',
                ],
                [
                    'key' => 'leads',
                    'label' => 'navigation.leads',
                    'icon' => 'user-plus',
                    'route' => '/leads',
                    'permission' => 'leads.view',
                    'children' => [
                        [
                            'key' => 'imports',
                            'label' => 'navigation.imports',
                            'icon' => 'file-up',
                            'route' => '/imports',
                            'permission' => 'leads.import',
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'opportunities',
            'label' => 'navigation.opportunities',
            'icon' => 'handshake',
            'route' => '/opportunities',
            'permission' => 'opportunities.view',
        ],
        [
            'key' => 'quotes',
            'label' => 'navigation.quotes',
            'icon' => 'file-text',
            'route' => '/quotes',
            'permission' => 'quotes.view',
        ],
        [
            'key' => 'request-management',
            'label' => 'navigation.requestManagement',
            'icon' => 'clipboard-list',
            'route' => '/request-management',
            'permission' => 'request-management.view',
            'children' => [
                [
                    'key' => 'field-change-requests',
                    'label' => 'navigation.fieldChangeRequests',
                    'icon' => 'list-checks',
                    'route' => '/field-change-requests',
                    'permission' => 'field-change-requests.view',
                ],
            ],
        ],
        [
            'key' => 'enrollee-management',
            'label' => 'navigation.enrolleeManagement',
            'icon' => 'user-plus',
            'route' => '/enrollee-management',
            'permission' => 'enrollee-management.view',
        ],
        [
            'key' => 'rewarded-referents',
            'label' => 'navigation.rewardedReferents',
            'icon' => 'handshake',
            'route' => '/rewarded-referents',
            'permission' => 'rewarded-referents.view',
        ],
        [
            'key' => 'request-statistics',
            'label' => 'navigation.requestStatistics',
            'icon' => 'chart-column',
            'route' => '/request-statistics',
            'permission' => 'request-statistics.view',
        ],
    ],
];
