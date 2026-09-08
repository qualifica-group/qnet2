<?php

use App\Http\Controllers\RequestManagement\ProductCategoryTabsController;
use App\Http\Controllers\RequestManagement\RequestManagementController;
use App\Http\Controllers\RequestManagement\RequestManagementDashboardController;
use App\Http\Controllers\RequestManagement\RequestManagementReportController;
use Illuminate\Support\Facades\Route;

// Request Management work panel (spec 0049; migrated onto the Quote by spec
// 0086).
//
// Dedicated create/show/update/delete plus bulk-assign endpoints for the
// operative "Gestione Richieste" panel: the record IS a Quote (spec 0086,
// D-1/D-2 — a grid row is a `quotes` record, not an `opportunities` one any
// more), but authorization/scoping run through the request-management
// permission set (RequestManagementPolicy/Scope), never the opportunities or
// quotes CRUD. Required from routes/api.php INSIDE the existing
// `auth:sanctum` group, so both routes inherit that middleware/prefix
// context. No list/export/activity route here: those are served by the
// generic tables/exports/activity-log framework (ADR 0002).
//
// Line comments only in this file, deliberately: a block comment (`/* */`)
// citing a permission-key glob like `resource.*` is one stray `/` away from
// closing itself early and invalidating the whole file (every route in it
// silently disappears, booting no error until something 404s).
// Declared BEFORE the {quote} routes: a POST to the literal segment must
// never be swallowed by the wildcard.
Route::post('request-management/assign-operators', [RequestManagementController::class, 'assignOperators']);
// Spec 0104 (direttiva utente 2026-09-07, moved onto position 1 by the
// direttiva utente 2026-09-08): the bulk GA1 assignment, the Sede-less
// sibling of assign-operators above. Same "declared before the
// wildcard" rule as every literal segment in this file.
Route::post('request-management/assign-manager-ga1', [RequestManagementController::class, 'assignManagerGa1']);
// Spec 0079: same "declared before the wildcard" rule as assign-operators
// above — a POST to this literal segment must never be swallowed by the
// GET/PUT/DELETE `{quote}` routes.
Route::post('request-management/transfer', [RequestManagementController::class, 'transfer']);
// Spec 0064 (M3): the category tab strip's data source — declared BEFORE
// GET /request-management/{quote} for the same reason as assign-operators
// above, otherwise "product-categories" is swallowed by the wildcard's route
// model binding.
Route::get('request-management/product-categories', ProductCategoryTabsController::class);
// User directive 2026-08-07: the create form's "Informazioni aggiuntive"
// preview. Same "declared before the wildcard" rule as every literal segment
// above.
Route::post('request-management/form-context', [RequestManagementController::class, 'formContext']);
// Spec 0057: the bare POST, gated by `request-management.create` — no
// {quote} to conflict with (creation), but declared here too for
// consistency with the file's own convention.
Route::post('request-management', [RequestManagementController::class, 'store']);
// Spec 0106: the CSV report's own create/poll/download endpoints. Same
// "declared before the wildcard" rule as every literal segment above.
Route::post('request-management/report', [RequestManagementReportController::class, 'store']);
// Spec 0106 rev-2 (D-14): the branch picker's data source. Declared BEFORE
// report/{exportRun} below — otherwise this literal segment is swallowed by
// that route's own wildcard, resolving to show() with exportRun="categories".
Route::get('request-management/report/categories', [RequestManagementReportController::class, 'categories']);
// Spec 0107 (D-1/D-5): the dashboard's own synchronous endpoint — same
// "declared before report/{exportRun}" rule as report/categories above.
Route::get('request-management/report/dashboard', RequestManagementDashboardController::class);

// Spec 0108: the GA2 Operatore the report may be filtered by. Same
// "declared before report/{exportRun}" rule as the two literal segments above.
Route::get('request-management/report/operators', [RequestManagementReportController::class, 'operators']);
// ->whereNumber() on top of the declaration order (rev-2 routing_trap):
// the order alone works until the file gets reorganised, the constraint
// does not.
Route::get('request-management/report/{exportRun}', [RequestManagementReportController::class, 'show'])->whereNumber('exportRun');
Route::get('request-management/report/{exportRun}/download', [RequestManagementReportController::class, 'download'])->whereNumber('exportRun');
Route::get('request-management/{quote}', [RequestManagementController::class, 'show']);
Route::delete('request-management/{quote}', [RequestManagementController::class, 'destroy']);
Route::match(['put', 'patch'], 'request-management/{quote}', [RequestManagementController::class, 'update']);
