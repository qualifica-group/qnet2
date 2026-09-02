<?php

use App\Http\Controllers\QuoteOfferLines\QuoteOfferLineForSelectController;
use App\Http\Controllers\WorkOrders\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Orders / "Commesse" (spec 0093)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/quotes.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| next-code (D-1) and quote-offer-lines/for-select (D-8) are declared ABOVE
| work-orders/{workOrder} so their literal segments win over the
| route-model-binding wildcard (mirrors quotes/next-code).
*/

Route::get('work-orders/next-code', [WorkOrderController::class, 'nextCode']);

// D-8: the selection-row endpoint feeding the WorkOrder form's multi-select,
// scoped to ONE quote's own REVENUE lines. Own top-level slug (not nested
// under work-orders/quotes): the semantics ("only offer lines of ONE quote")
// are narrow and the name must say so.
Route::get('quote-offer-lines/for-select', QuoteOfferLineForSelectController::class);

// WorkOrders CRUD. Authorization (work-orders.view/create/update/delete) is
// enforced server-side in WorkOrderController via WorkOrderPolicy on every
// endpoint.
Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show']);
Route::post('work-orders', [WorkOrderController::class, 'store']);
Route::match(['put', 'patch'], 'work-orders/{workOrder}', [WorkOrderController::class, 'update']);
Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy']);
