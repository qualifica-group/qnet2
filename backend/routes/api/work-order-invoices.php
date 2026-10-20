<?php

use App\Http\Controllers\WorkOrders\WorkOrderInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order proforma/invoices view (spec 0282)
|--------------------------------------------------------------------------
|
| Required from routes/api/work-orders.php, so it inherits the same
| `auth:sanctum` group. Authorization is enforced server-side
| (WorkOrderPolicy::viewInvoices); read-only, no throttle (backend.md §2).
*/

Route::get('work-orders/{workOrder}/invoices', [WorkOrderInvoiceController::class, 'show']);
