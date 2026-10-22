<?php

use App\Http\Controllers\CompanyClosures\CompanyClosureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company closures catalogue (spec 0304 D-13)
|--------------------------------------------------------------------------
|
| Authorization (company-closures.view/create/update/delete) is enforced
| server-side in the controller via CompanyClosurePolicy. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group; the list is the generic
| `company-closures` table domain.
*/

Route::get('company-closures/{companyClosure}', [CompanyClosureController::class, 'show']);
Route::post('company-closures', [CompanyClosureController::class, 'store']);
Route::match(['put', 'patch'], 'company-closures/{companyClosure}', [CompanyClosureController::class, 'update']);
Route::delete('company-closures/{companyClosure}', [CompanyClosureController::class, 'destroy']);
