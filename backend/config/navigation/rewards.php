<?php

// "Premi e Incentivi": the reward domain promoted to its own top-level
// collapsible group (user decision 2026-07-24). Gathers the read-only
// "Referenti con Buoni" aggregated view (spec 0059), the "Buoni,
// Premi e Incentivi" type catalogue (spec 0058) and the "Stati Buoni
// Collegati" state pick-list (spec 0060) — previously scattered under
// Anagrafiche and Configurazione. Route-less parent:
// renders collapsible, dropped when the actor can see no child.
return [
    'key' => 'rewards-group',
    'label' => 'navigation.rewards',
    'icon' => 'award',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            // Rewarded referents (spec 0059): the aggregated, READ-ONLY
            // view (D-6) over Referenti holding at least one Reward.
            // Gated by its OWN `rewarded-referents.*` set, never
            // `referents.*` (precedent request-management).
            'key' => 'rewarded-referents',
            'label' => 'navigation.rewardedReferents',
            'icon' => 'handshake',
            'route' => '/rewarded-referents',
            'permission' => 'rewarded-referents.view',
        ],
        [
            // Reward types (spec 0058): the "Buoni, Premi e Incentivi"
            // configuration pick-list — the type catalogue for the
            // reward-assignment flows (D-1/D-7), not the assigned
            // rewards themselves.
            'key' => 'reward-types',
            'label' => 'navigation.rewardTypes',
            'icon' => 'gift',
            'route' => '/reward-types',
            'permission' => 'reward-types.view',
        ],
        [
            // Reward statuses (spec 0060): the STATE pick-list for
            // assigned rewards, delete-guarded (BR-4). Moved here from
            // `configuration` (user decision 2026-07-27): it belongs to
            // the reward domain, next to its type catalogue.
            'key' => 'reward-statuses',
            'label' => 'navigation.rewardStatuses',
            'icon' => 'list-checks',
            'route' => '/reward-statuses',
            'permission' => 'reward-statuses.view',
        ],
    ],
];
