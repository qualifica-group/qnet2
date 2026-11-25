<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assignment date (task 428419)
    |--------------------------------------------------------------------------
    |
    | Flexible fields (attribute codes) of the commessa set to today when a
    | Responsabile is added to it, as the legacy did for its "Data di
    | assegnazione" (`orderisos.data_inizio_lavorazione`). Only the codes the
    | commessa's own categories carry are written. "Today" is the office's
    | day, not the UTC one of the application clock.
    |
    */

    'assignment_date_attribute_codes' => ['data_inizio_lavorazione', 'iso_assignment_date'],

    'assignment_date_timezone' => 'Europe/Rome',

    /*
    |--------------------------------------------------------------------------
    | Cost VAT rate (user decision 2026-11-24)
    |--------------------------------------------------------------------------
    |
    | Costs carry no VAT, as in the legacy: offer cost lines and work order
    | costs start on this 0% rate (looked up by name, its id differs per
    | environment), and `costs:clear-vat` moves the existing ones onto it.
    |
    */

    'cost_vat_rate_name' => env('COST_VAT_RATE_NAME', 'IVA al 0%'),

];
