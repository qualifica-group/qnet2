<?php

return [
    'GET /stock-balances' => [
        'summary' => 'List stock balances',
        'description' => 'Returns the stock quantities held per product, operational site and optional user (free stock of the site when user is null), with the date of the last movement, ordered by site, then free before assigned stock, then product. At least one filter among product_id, user_id and operational_site_id is required (422 otherwise). Not paginated. Requires stock.viewAny or stock.manage.',
    ],
];
