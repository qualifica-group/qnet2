<?php

// Formazione (spec 0284 D-6): top-level item after Assenze, gated by the
// module's own entry permission like every other top-level module;
// `open_to_managers` (spec 0309) also opens it to anyone with active subordinates.
return [
    'key' => 'staff-trainings',
    'label' => 'navigation.staffTrainings',
    'icon' => 'graduation-cap',
    'route' => '/staff-trainings',
    'permission' => 'staff-trainings.viewAny',
    'open_to_managers' => true,
];
