<?php

use App\Http\Controllers\Identity\IdentityDuplicateCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity duplicate check
|--------------------------------------------------------------------------
|
| Live, non-blocking duplicate check shared by the anagrafica and referente
| create forms (spec 0037, extended by the user directive 2026-09-09 to CF,
| P.IVA and the whole identity namespace). Required from routes/api.php INSIDE
| the auth:sanctum group; authorization is enforced in the controller.
|
*/

Route::post('identity/duplicate-check', IdentityDuplicateCheckController::class);
