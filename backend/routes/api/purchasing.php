<?php

/*
|--------------------------------------------------------------------------
| Purchasing section routes
|--------------------------------------------------------------------------
|
| Purchase requests (RDA, spec 0208), cost types and purchase orders (ODA,
| spec 0210). Grouped here to keep routes/api.php under the file-size limit
| (engineering.md §6); required INSIDE the auth:sanctum group of
| routes/api.php, so every route still inherits that context.
|
*/

require __DIR__.'/purchase-requests.php';
require __DIR__.'/cost-types.php';
require __DIR__.'/purchase-request-priorities.php';
require __DIR__.'/purchase-order-statuses.php';
require __DIR__.'/purchase-request-line-statuses.php';
require __DIR__.'/purchase-orders.php';
require __DIR__.'/purchase-order-emails.php';
require __DIR__.'/purchase-invoices.php';
