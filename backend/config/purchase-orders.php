<?php

return [
    // Max distinct purchase orders in one bulk status change (spec 0433, D-3).
    'bulk_status_max' => (int) env('PURCHASE_ORDERS_BULK_STATUS_MAX', 200),
];
