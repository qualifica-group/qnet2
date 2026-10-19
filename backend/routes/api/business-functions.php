<?php

use App\Http\Controllers\BusinessFunctions\BusinessFunctionController;
use App\Http\Controllers\BusinessFunctions\BusinessFunctionForSelectController;
use Illuminate\Support\Facades\Route;

// Business functions CRUD backing the table row-actions (view/edit/delete)
// + create (spec 0010). Authorization (business-functions.view/create/
// update/delete) is enforced server-side in BusinessFunctionController via
// BusinessFunctionPolicy on every endpoint.
// Minimal searchable/paginated business-function list for entity-backed
// selects (for-select standard, ADR 0011), feeding the spec 0015 user-
// form "function" select. Declared ABOVE business-functions/{businessFunction}
// so the literal `for-select` segment wins over the bound wildcard.
// The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('business-functions/for-select', BusinessFunctionForSelectController::class);

Route::get('business-functions/{businessFunction}', [BusinessFunctionController::class, 'show']);
Route::post('business-functions', [BusinessFunctionController::class, 'store']);
Route::match(['put', 'patch'], 'business-functions/{businessFunction}', [BusinessFunctionController::class, 'update']);
Route::delete('business-functions/{businessFunction}', [BusinessFunctionController::class, 'destroy']);
