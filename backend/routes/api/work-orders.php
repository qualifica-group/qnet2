<?php

use App\Http\Controllers\QuoteOfferLines\QuoteOfferLineForSelectController;
use App\Http\Controllers\WorkOrders\WorkOrderController;
use App\Http\Controllers\WorkOrders\WorkOrderForSelectController;
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

// Spec 0101 (T-04b): the Commessa option list the Task form's `work_order_id`
// picker consumes — spec 0093 never shipped one. Declared ABOVE
// work-orders/{workOrder} so the literal segment wins over the
// route-model-binding wildcard. Gated by auth:sanctum only (ADR 0011), but
// the rows are narrowed by WorkOrderVisibilityScope inside the Service, the
// same scope WorkOrdersTableDefinition::baseQuery() applies to the grid.
Route::get('work-orders/for-select', WorkOrderForSelectController::class);

Route::get('work-orders/next-code', [WorkOrderController::class, 'nextCode']);

// D-8: the selection-row endpoint feeding the WorkOrder form's multi-select,
// scoped to ONE quote's own REVENUE lines. Own top-level slug (not nested
// under work-orders/quotes): the semantics ("only offer lines of ONE quote")
// are narrow and the name must say so.
Route::get('quote-offer-lines/for-select', QuoteOfferLineForSelectController::class);

// Spec 0098, D-7: live preview of the dynamic "Informazioni aggiuntive" the
// composed `quote_line_ids` resolve to, for BOTH the WorkOrder form and the
// Contract's "Programma" dialog — declared ABOVE work-orders/{workOrder} so
// the literal segment wins over the route-model-binding wildcard (mirrors
// quotes/form-context).
Route::post('work-orders/form-context', [WorkOrderController::class, 'formContext']);

// WorkOrders CRUD. Authorization (work-orders.view/create/update/delete) is
// enforced server-side in WorkOrderController via WorkOrderPolicy on every
// endpoint.
Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show']);
Route::post('work-orders', [WorkOrderController::class, 'store']);
Route::match(['put', 'patch'], 'work-orders/{workOrder}', [WorkOrderController::class, 'update']);
Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy']);
