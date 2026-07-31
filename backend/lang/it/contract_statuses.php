<?php

return [

    // BR-5 (spec 0072): thrown by
    // App\Services\Contracts\ContractStatusDefaultManager as a 422
    // ValidationException keyed on `is_active`/`is_default` — same shape as
    // App\Services\DocumentLayouts\DocumentLayoutDefaultManager (spec 0069).
    'default_requires_active' => 'Uno stato predefinito deve essere attivo.',
    'default_cannot_be_deactivated' => 'Lo stato predefinito non può essere disattivato: designa prima un altro stato come predefinito.',
    'default_must_be_reassigned' => 'Lo stato predefinito non può essere rimosso direttamente: designa prima un altro stato come predefinito.',

];
