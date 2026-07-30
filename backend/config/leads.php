<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk lead -> opportunity conversion (spec 0071)
    |--------------------------------------------------------------------------
    |
    | How many leads a single POST /api/leads/convert-to-opportunities accepts.
    | The conversion is synchronous and all-or-nothing (D-1/D-3): every lead in
    | the batch inserts an Opportunity plus its product line inside ONE
    | transaction, so this cap is what keeps a very large grid selection from
    | running into the request timeout. Enforced by BulkConvertLeadsRequest's
    | `max:` rule, never hard-coded inline.
    |
    */

    'bulk_conversion_max' => (int) env('LEADS_BULK_CONVERSION_MAX', 200),

];
