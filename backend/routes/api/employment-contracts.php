<?php

use App\Http\Controllers\EmploymentContracts\EmploymentContractController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Employment contract history (spec 0272)
|--------------------------------------------------------------------------
|
| Required from routes/api/hr.php, inside the `auth:sanctum` group.
| Authorization is enforced server-side (UserPolicy view/update, plus
| `users.manageCompensation` for salary fields in the store request); no
| throttle (backend.md §2). The condition is looked up through the user's own
| employment profile, so another person's condition is a 404 (see the
| controller).
*/

Route::get('users/{user}/employment-contracts', [EmploymentContractController::class, 'index']);
Route::post('users/{user}/employment-contracts', [EmploymentContractController::class, 'store']);
Route::delete('users/{user}/employment-contracts/{employmentContract}', [EmploymentContractController::class, 'destroy']);
