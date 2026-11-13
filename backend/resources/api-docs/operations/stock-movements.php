<?php

return [
    'GET /stock-movements' => [
        'summary' => 'List the movement history of a stock balance',
        'description' => 'Returns the latest 200 movements, newest first, in which one stock holding was source or destination. The holding is product_id plus operational_site_id (both required) plus optional user_id: without user_id it is the stock kept at the site, with it the stock assigned to that person at the site. Requires stock.viewAny or stock.manage.',
    ],
    'POST /stock-movements' => [
        'summary' => 'Record a stock movement',
        'description' => 'Records a movement of a stock-tracked product (physical, for internal use; 422 otherwise) and updates the balances. type: LOAD into operational_site_id; UNLOAD (note required); TRANSFER to to_operational_site_id; ASSIGN from the site to user_id; RETURN from user_id back to the site; ADJUSTMENT with counted_quantity instead of quantity (note required, the difference is recorded). quantity > 0, up to 3 decimals; moved_on defaults to today, never in the future. 422 when stock is insufficient or goes to an inactive site or user. Returns 201 with the movement and the updated balances. Requires stock.manage.',
    ],
];
