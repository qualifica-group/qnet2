<?php

// "Opportunita' e Commesse" (spec 0043, D-4): Opportunities (spec 0040,
// a commercial deal against an Anagrafica, created manually or
// generated from a Lead) gathered under one collapsible parent with
// their own working-state pick-list (opportunity statuses). No
// "Commesse" module exists yet in this iteration — only the group's
// name anticipates it. A route-less parent with children renders as a
// collapsible group; it is dropped automatically when the actor can
// see none of its children.
return [
    'key' => 'opportunities-group',
    'label' => 'navigation.opportunitiesAndCommesse',
    'icon' => 'briefcase',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'opportunities',
            'label' => 'navigation.opportunities',
            'icon' => 'handshake',
            'route' => '/opportunities',
            'permission' => 'opportunities.view',
        ],
        [
            // Opportunity statuses (spec 0043): the Opportunity
            // working-state pick-list, delete-guarded (BR-2).
            'key' => 'opportunity-statuses',
            'label' => 'navigation.opportunityStatuses',
            'icon' => 'tag',
            'route' => '/opportunity-statuses',
            'permission' => 'opportunity-statuses.view',
        ],
        [
            // Opportunity workflow configurator (spec 0047): a NEW,
            // separate "stato di lavorazione" dimension (distinct
            // from the pipeline opportunity-statuses above),
            // criteria-matched per Opportunity.
            'key' => 'opportunity-workflows',
            'label' => 'navigation.opportunityWorkflows',
            'icon' => 'workflow',
            'route' => '/opportunity-workflows',
            'permission' => 'opportunity-workflows.viewAny',
        ],
        [
            // Quote statuses (spec 0065): the Quote working-state
            // pick-list, a plain clone of opportunity-statuses.
            'key' => 'quote-statuses',
            'label' => 'navigation.quoteStatuses',
            'icon' => 'tag',
            'route' => '/quote-statuses',
            'permission' => 'quote-statuses.view',
        ],
        [
            // Quotes/Offers (spec 0065, MT-05): quotes against an
            // Opportunity, gated by their own `quotes.*` permission set.
            'key' => 'quotes',
            'label' => 'navigation.quotes',
            'icon' => 'file-text',
            'route' => '/quotes',
            'permission' => 'quotes.view',
        ],
        [
            // Contract statuses (spec 0072): the Contract working-state
            // pick-list (BR-5 exclusive default), same
            // status-before-its-entity placement as quote-statuses
            // above quotes.
            'key' => 'contract-statuses',
            'label' => 'navigation.contractStatuses',
            'icon' => 'tag',
            'route' => '/contract-statuses',
            'permission' => 'contract-statuses.view',
        ],
        [
            // Contracts (spec 0072): the additional lifecycle data
            // (validation/scheduling/termination/reactivation) for a
            // Quote that reached `closed_won` — never created/deleted
            // by hand (D-6), gated by its own `contracts.*` set.
            // Icon: 'files' (already mapped in icon-map.ts) rather
            // than 'file-signature'/'file-check' — neither is mapped
            // yet (MT-09's map), and an unmapped name would silently
            // fall back to the neutral Circle icon.
            'key' => 'contracts',
            'label' => 'navigation.contracts',
            'icon' => 'files',
            'route' => '/contracts',
            'permission' => 'contracts.view',
        ],
        [
            // Request Management (spec 0049): the operative
            // "Gestione Richieste" view over Opportunities for
            // commercial operators (D-1, no new entity). Gated by its
            // OWN `request-management.*` permission set, never
            // `opportunities.*`.
            'key' => 'request-management',
            'label' => 'navigation.requestManagement',
            'icon' => 'clipboard-list',
            'route' => '/request-management',
            'permission' => 'request-management.view',
        ],
        [
            // Field change requests (spec 0078): the dedicated browse
            // view over proposed changes to a protected field (today:
            // request-management's "Fonte"), gated by its OWN
            // `field-change-requests.*` set.
            'key' => 'field-change-requests',
            'label' => 'navigation.fieldChangeRequests',
            'icon' => 'list-checks',
            'route' => '/field-change-requests',
            'permission' => 'field-change-requests.view',
        ],
    ],
];
