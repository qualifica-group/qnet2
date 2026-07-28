<?php

use App\Http\Controllers\RequestManagement\ProductCategoryTabsController;
use App\Http\Controllers\RequestManagement\RequestManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Request Management work panel (spec 0049)
|--------------------------------------------------------------------------
|
| Dedicated create/show/update/delete plus bulk-assign endpoints for the
| operative "Gestione Richieste"
| panel: the record IS an Opportunity (D-1), but authorization/scoping run
| through `request-management.*` (RequestManagementPolicy/Scope), never the
| opportunities.* CRUD (PATCH /api/opportunities/{id}). Required from routes/api.php
| INSIDE the existing `auth:sanctum` group, so both routes inherit that
| middleware/prefix context. No list/export/activity route here: those are
| served by the generic tables/exports/activity-log framework (ADR 0002).
*/
// Declared BEFORE the {opportunity} routes: a POST to the literal segment
// must never be swallowed by the wildcard.
Route::post('request-management/assign-operators', [RequestManagementController::class, 'assignOperators']);
// Spec 0064 (M3): the category tab strip's data source — declared BEFORE
// GET /request-management/{opportunity} for the same reason as
// assign-operators above, otherwise "product-categories" is swallowed by the
// wildcard's route model binding.
Route::get('request-management/product-categories', ProductCategoryTabsController::class);
// Spec 0057: the bare POST, gated by `request-management.create` — no
// {opportunity} to conflict with (creation), but declared here too for
// consistency with the file's own convention.
Route::post('request-management', [RequestManagementController::class, 'store']);
Route::get('request-management/{opportunity}', [RequestManagementController::class, 'show']);
Route::delete('request-management/{opportunity}', [RequestManagementController::class, 'destroy']);
Route::match(['put', 'patch'], 'request-management/{opportunity}', [RequestManagementController::class, 'update']);
