<?php

use App\Http\Controllers\FieldChangeRequests\FieldChangeRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Generic field-change-request lifecycle (spec 0078)
|--------------------------------------------------------------------------
|
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route below inherits that middleware/prefix context. No `throttle`
| (backend.md §2, decision 2026-07-15). The dedicated browse table
| (field-change-requests columns/rows) is served by the generic
| tables/activity-log frameworks (config/tables.php,
| config/activity-log.php) — no route here.
*/
// Literal segment declared BEFORE the {fieldChangeRequest} wildcard below,
// otherwise "for-record" would be swallowed by the route model binding.
Route::get('field-change-requests/for-record', [FieldChangeRequestController::class, 'forRecord']);
Route::post('field-change-requests', [FieldChangeRequestController::class, 'store']);
Route::get('field-change-requests/{fieldChangeRequest}', [FieldChangeRequestController::class, 'show']);
Route::post('field-change-requests/{fieldChangeRequest}/approve', [FieldChangeRequestController::class, 'approve']);
Route::post('field-change-requests/{fieldChangeRequest}/reject', [FieldChangeRequestController::class, 'reject']);
