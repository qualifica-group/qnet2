<?php

// Assenze (spec 0278 D-13): top-level item next to the Segnatempo, gated by the
// module's own entry permission like every other top-level module.
return [
    'key' => 'absences',
    'label' => 'navigation.absences',
    'icon' => 'calendar-off',
    'route' => '/absences',
    'permission' => 'absence-requests.viewAny',
];
