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

];
