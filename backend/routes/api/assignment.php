<?php

use App\Http\Controllers\Assignment\SelectionScopeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cross-domain assignment routes (spec 0110, extended by 0113)
|--------------------------------------------------------------------------
|
| Extracted from routes/api.php (engineering.md §6, that file is at the
| 500-line hard limit) — required from WITHIN its `auth:sanctum` group, so
| every route below inherits it exactly as if inlined.
|
| The assignment scope of a SELECTION of records — required categories,
| shared Sede, campaigns spanned — for the three assignment surfaces at once
| (import rows, leads, offers). POST, not GET:
| the selection it reads is a body (AG Grid's select_all/row_ids, or an id
| list), not something a query string can carry. Authorization is the READ
| gate of the requested `domain`, enforced server-side in the controller —
| the endpoint mints no permission of its own.
*/
Route::post('assignment/selection-scope', SelectionScopeController::class);
