<?php

use App\Http\Controllers\CategoryTabs\GridCategoryTabPreferencesController;
use App\Http\Controllers\CategoryTabs\GridCategoryTabsController;
use App\Http\Controllers\Opportunities\OpportunityController;
use App\Http\Controllers\Opportunities\OpportunityForSelectController;
use App\Tables\CategoryTabs\CategoryTabGrid;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Opportunities (spec 0040)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already near the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| for-select (spec 0059/MT-10): initially out of scope (spec 0040), added to
| feed the `rewarded-referents` "opportunity" advanced filter. Declared
| ABOVE opportunities/{opportunity} so the literal segment wins over the
| route-model-binding wildcard (mirrors leads/for-select, products/for-select).
*/

Route::get('opportunities/for-select', OpportunityForSelectController::class);

// Spec 0335: the category tab strip of the grid (counts + the actor's
// favourites). Declared BEFORE the wildcard, otherwise "product-categories"
// is swallowed by its route model binding.
Route::get('opportunities/product-categories', GridCategoryTabsController::class)
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Opportunities->value);
Route::get('opportunities/category-tab-preferences', [GridCategoryTabPreferencesController::class, 'show'])
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Opportunities->value);
Route::put('opportunities/category-tab-preferences', [GridCategoryTabPreferencesController::class, 'update'])
    ->defaults(CategoryTabGrid::ROUTE_DEFAULT, CategoryTabGrid::Opportunities->value);

// Opportunities CRUD. Authorization (opportunities.view/create/update/
// delete) is enforced server-side in OpportunityController via
// OpportunityPolicy on every endpoint.
Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show']);
Route::post('opportunities', [OpportunityController::class, 'store']);
Route::match(['put', 'patch'], 'opportunities/{opportunity}', [OpportunityController::class, 'update']);
Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy']);
