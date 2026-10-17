<?php

use App\Http\Controllers\OrgChart\OrgChartController;
use App\Http\Controllers\OrgChart\OrgChartMoveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Org chart (spec 0273)
|--------------------------------------------------------------------------
|
| Reads are gated by `org-chart.view`; each move by the permissions of the
| data it edits (users.update / business-functions.update plus the field
| permission), all enforced server-side in the controllers and FormRequests. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group.
*/

Route::get('org-chart/functions', [OrgChartController::class, 'functions']);
Route::get('org-chart/people', [OrgChartController::class, 'people']);
Route::post('org-chart/people/{user}/manager', [OrgChartMoveController::class, 'manager']);
Route::post('org-chart/functions/{businessFunction}/parent', [OrgChartMoveController::class, 'parent']);
Route::post('org-chart/people/{user}/function', [OrgChartMoveController::class, 'personFunction']);
