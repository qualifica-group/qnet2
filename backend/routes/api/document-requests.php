<?php

use App\Http\Controllers\DocumentRequests\DocumentRequestActionController;
use App\Http\Controllers\DocumentRequests\DocumentRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document requests / "Richieste documenti" (spec 0428)
|--------------------------------------------------------------------------
|
| Requests of documents of a work order, kept in step with ArchivioSmart.
| Required from routes/api/archivio-smart.php INSIDE the existing `auth:sanctum`
| group. No throttle (backend.md section 2). Authorization (document-requests.*
| plus view on the work order) is enforced in the controllers via
| DocumentRequestPolicy. The inbound webhook is in routes/api/webhooks.php.
*/

Route::get('work-orders/{workOrder}/document-requests', [DocumentRequestController::class, 'index']);
Route::get('work-orders/{workOrder}/document-requests/defaults', [DocumentRequestController::class, 'defaults']);
Route::post('work-orders/{workOrder}/document-requests', [DocumentRequestController::class, 'store']);

Route::get('document-requests/{documentRequest}', [DocumentRequestController::class, 'show']);
Route::put('document-requests/{documentRequest}', [DocumentRequestController::class, 'update']);
Route::get('document-requests/{documentRequest}/sync-logs', [DocumentRequestController::class, 'logs']);
Route::post('document-requests/{documentRequest}/remind', [DocumentRequestActionController::class, 'remind']);
Route::post('document-requests/{documentRequest}/cancel', [DocumentRequestActionController::class, 'cancel']);
Route::post('document-requests/{documentRequest}/refresh', [DocumentRequestActionController::class, 'refresh']);
