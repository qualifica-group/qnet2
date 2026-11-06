<?php

use App\Http\Controllers\AgentSalesReport\AgentSalesReportExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Report Vendite Agenti (spec 0365)
|--------------------------------------------------------------------------
|
| Authorization: agent-sales-report.view (FormRequest::authorize), rows limited by
| QuoteVisibilityScope. The `domain` default lets the shared export validation resolve
| the table definition. Required from routes/api.php INSIDE the `auth:sanctum` group.
*/

Route::post('agent-sales-report/exports/details', [AgentSalesReportExportController::class, 'details'])
    ->defaults('domain', 'agent-sales-report');
Route::post('agent-sales-report/exports/lines', [AgentSalesReportExportController::class, 'lines'])
    ->defaults('domain', 'agent-sales-report');
