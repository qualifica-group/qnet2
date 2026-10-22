<?php

/*
|--------------------------------------------------------------------------
| Absence requests (spec 0278)
|--------------------------------------------------------------------------
|
| Authorization: absence-requests.viewAny/create/manageAll (AbsenceRequestPolicy).
| Required from routes/api/hr.php INSIDE the `auth:sanctum` group.
| Table endpoints are added by a later microtask.
*/

use App\Http\Controllers\AbsenceRequests\AbsenceInsightController;
use App\Http\Controllers\AbsenceRequests\AbsenceRequestAttachmentController;
use App\Http\Controllers\AbsenceRequests\AbsenceRequestBulkDecisionController;
use App\Http\Controllers\AbsenceRequests\AbsenceRequestController;
use App\Http\Controllers\AbsenceRequests\AbsenceRequestTransitionController;
use App\Http\Controllers\AbsenceRequests\AbsenceSummaryController;
use Illuminate\Support\Facades\Route;

// Static segments first: spec 0304 (preview, bulk decision, summary, insight).
Route::post('absence-requests/preview', [AbsenceInsightController::class, 'preview']);
Route::post('absence-requests/bulk-decision', AbsenceRequestBulkDecisionController::class);
Route::get('absences/summary', AbsenceSummaryController::class);
Route::get('absence-requests/{absenceRequest}/insight', [AbsenceInsightController::class, 'show']);

Route::get('absence-requests/{absenceRequest}', [AbsenceRequestController::class, 'show']);
Route::post('absence-requests', [AbsenceRequestController::class, 'store']);
Route::patch('absence-requests/{absenceRequest}', [AbsenceRequestController::class, 'update']);
Route::delete('absence-requests/{absenceRequest}', [AbsenceRequestController::class, 'destroy']);

Route::post('absence-requests/{absenceRequest}/submit', [AbsenceRequestTransitionController::class, 'submit']);
Route::post('absence-requests/{absenceRequest}/approve', [AbsenceRequestTransitionController::class, 'approve']);
Route::post('absence-requests/{absenceRequest}/reject', [AbsenceRequestTransitionController::class, 'reject']);
Route::post('absence-requests/{absenceRequest}/cancel', [AbsenceRequestTransitionController::class, 'cancel']);

Route::post('absence-requests/{absenceRequest}/attachment', [AbsenceRequestAttachmentController::class, 'store']);
Route::get('absence-requests/{absenceRequest}/attachment', [AbsenceRequestAttachmentController::class, 'show']);
Route::delete('absence-requests/{absenceRequest}/attachment', [AbsenceRequestAttachmentController::class, 'destroy']);
