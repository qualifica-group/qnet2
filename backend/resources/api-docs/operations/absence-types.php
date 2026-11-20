<?php

return [
    'POST /absence-types' => [
        'summary' => 'Create an absence type',
        'description' => 'Creates an absence type (e.g. holiday, leave) in the catalogue employees pick from when filing an absence request. name must be unique; deducts_balance and requires_approval are required. At least one of allows_full_day, allows_half_day, allows_hourly must stay true, and default_annual_days is accepted only on a type that deducts the balance (422 otherwise); color is one of slate, gray, amber, blue, emerald, green, red, violet. Returns 201 with the type and the actor\'s permissions block. Requires absence-types.create.',
    ],
    'GET /absence-types/for-select' => [
        'summary' => 'List absence types for a select',
        'description' => 'Returns the active absence types in catalogue order (sort_order, then name) with the flags needed by the request form: whether they deduct the balance, need approval, which durations are allowed (full day, half day, hourly), minimum notice days and color. Pass ids[] to also get saved types that are now inactive, appended after the active ones. Requires authentication only.',
    ],
    'GET /absence-types/{absenceType}' => [
        'summary' => 'Get an absence type',
        'description' => 'Returns the absence type with its rules (balance deduction, approval, allowed durations, minimum notice, default annual days, color, active flag, order) and the actor\'s permissions block. Requires absence-types.view.',
    ],
    'PUT /absence-types/{absenceType}' => [
        'summary' => 'Update an absence type',
        'description' => 'Updates the absence type; every field is optional (partial update). The same cross-field rules as on create apply to the resulting values: at least one allowed duration, and a default annual allowance only on a type that deducts the balance (422 otherwise). Returns the updated type and the permissions block. Requires absence-types.update.',
    ],
    'DELETE /absence-types/{absenceType}' => [
        'summary' => 'Delete an absence type',
        'description' => 'Deletes the absence type. Refused with 409, listing the uses, when it is still referenced by absence allowances, absence requests or company closures: deactivate it (is_active false) instead. Returns 204 no content. Requires absence-types.delete.',
    ],
];
