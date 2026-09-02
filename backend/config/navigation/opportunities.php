<?php

// "Opportunita' e Commesse" (spec 0043, D-4): Opportunities (spec 0040,
// a commercial deal against an Anagrafica, created manually or
// generated from a Lead) gathered under one collapsible parent. Spec
// 0082 removed the opportunity-statuses pick-list from this group: an
// Opportunity's status is computed from its Quotes' statuses. A
// route-less parent with children renders as a collapsible group; it is
// dropped automatically when the actor can see none of its children.
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
            // Quote workflow configurator (spec 0047, moved onto the
            // Offerta by spec 0083 D-6): "Configuratore Stati Offerta"
            // — the Offerta's own working-state dimension, criteria-
            // matched per Quote, and (D-8) the source of the
            // Opportunity's own computed status when it has no Offerta.
            'key' => 'quote-workflows',
            'label' => 'navigation.quoteWorkflows',
            'icon' => 'workflow',
            'route' => '/quote-workflows',
            'permission' => 'quote-workflows.viewAny',
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
            // Work orders / "Commesse" (spec 0093): one or more product
            // lines of a single Offerta, gated by their own `work-orders.*`
            // permission set. Placed right after Offerte, the entity it is
            // always scoped to.
            'key' => 'work-orders',
            'label' => 'navigation.workOrders',
            'icon' => 'clipboard-list',
            'route' => '/work-orders',
            'permission' => 'work-orders.view',
        ],
        [
            // Contract statuses (spec 0072): the Contract working-state
            // pick-list (BR-5 exclusive default), same
            // status-before-its-entity placement as quote-workflows
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
            // Navigable parent: clicking it opens the worklist
            // (/request-management); the change-request queue hangs
            // beneath it (user decision 2026-08-04), the same shape as
            // Leads -> Import.
            'key' => 'request-management',
            'label' => 'navigation.requestManagement',
            'icon' => 'clipboard-list',
            'route' => '/request-management',
            'permission' => 'request-management.view',
            'children' => [
                [
                    // Field change requests (spec 0078): the dedicated
                    // browse view over proposed changes to a protected
                    // field, gated by its OWN `field-change-requests.*`
                    // set. Nested here rather than a sibling of the
                    // group because the only protected field today is
                    // this module's "Fonte" (ProtectedFieldRegistry) —
                    // the queue is a satellite of the worklist, not a
                    // module of its own. Safe under
                    // NavigationService::filter(), which drops a child
                    // with a denied parent: the page is supervisor-only
                    // (that role holds `request-management.view` too),
                    // and the Commercial role never had
                    // `field-change-requests.view` to begin with.
                    'key' => 'field-change-requests',
                    'label' => 'navigation.fieldChangeRequests',
                    'icon' => 'list-checks',
                    'route' => '/field-change-requests',
                    'permission' => 'field-change-requests.view',
                ],
            ],
        ],
    ],
];
