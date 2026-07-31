<?php

return [

    // BR-5 (spec 0072): thrown by
    // App\Services\Contracts\ContractStatusDefaultManager as a 422
    // ValidationException keyed on `is_active`/`is_default` — same shape as
    // App\Services\DocumentLayouts\DocumentLayoutDefaultManager (spec 0069).
    'default_requires_active' => 'A default status must be active.',
    'default_cannot_be_deactivated' => 'The default status cannot be deactivated: designate another status as default first.',
    'default_must_be_reassigned' => 'The default status cannot be unset directly: designate another status as default first.',

];
