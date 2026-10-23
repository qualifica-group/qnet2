<?php

// Competenze (spec 0286 D-7): top-level item after Formazione, gated by the
// module's own entry permission like every other top-level module;
// `open_to_managers` (spec 0309) also opens it to anyone with active subordinates.
return [
    'key' => 'user-skills',
    'label' => 'navigation.userSkills',
    'icon' => 'sparkles',
    'route' => '/user-skills',
    'permission' => 'user-skills.viewAny',
    'open_to_managers' => true,
];
