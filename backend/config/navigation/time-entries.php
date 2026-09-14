<?php

// Segnatempo (spec 0122, D-1): its own top-level item, not a child of "Task" —
// D-1 explicitly breaks from the standard AG Grid + RecordCanvas shape every
// other module (including Task) follows, so it does not belong under the
// tasks-group either. `permission` is `time-entries.viewAny`: the dashboard's
// own entry gate, exactly like every other module's top-level item.
return [
    'key' => 'time-entries',
    'label' => 'navigation.timeEntries',
    'icon' => 'clock',
    'route' => '/time-entries',
    'permission' => 'time-entries.viewAny',
];
