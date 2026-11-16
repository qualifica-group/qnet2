<?php

return [
    'GET /quote-offer-lines/for-select' => [
        'summary' => 'List the revenue lines of an offer for a select',
        'description' => 'Returns the revenue lines of one offer (quote_id, required) as select options (id, product code and name as label, sort order), in offer order, for picking the lines a work order covers. Cost lines are never offered, nor lines already programmed in another work order for the same renewal period, except those of except_work_order_id (the work order being edited). search matches product code and name; offset, limit, ids[] (hydration) and include_total=0 (has_more instead of the count) as usual. Responds with items and pagination. Requires work-orders.view or quotes.view.',
    ],
];
