<?php

use App\Http\Controllers\Table\TableFilterViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Saved filter views (spec 0007, extended by spec 0158)
|--------------------------------------------------------------------------
|
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route here inherits that middleware (engineering.md §6, file-size
| split out of routes/api.php's 500-line hard limit).
|
| Named, savable AG Grid filter sets per domain, private or shared, plus
| (spec 0158) a generic E/O custom filter rule set and per-user favorites.
| List/create are gated by the same definition viewAny; update/delete are
| gated by TableFilterViewPolicy (owner only — a shared view is a real
| cross-user access surface); store/update additionally require
| `table-filter-views.publish` for `visibility: shared`. A bound
| {filterView} whose domain does not match {domain} 404s (never 403), so
| views never leak across domains — favorite/unfavorite apply the same rule
| PLUS visibility (own or `shared`), so another user's private view 404s too.
*/
Route::get('tables/{domain}/filter-views', [TableFilterViewController::class, 'index']);
Route::post('tables/{domain}/filter-views', [TableFilterViewController::class, 'store']);
Route::put('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'update'])
    ->scopeBindings();
Route::delete('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'destroy'])
    ->scopeBindings();

// Per-user favorites (spec 0158, D-4): idempotent toggle on a visible view
// (own or `shared`) — a view of another domain, or another user's PRIVATE
// view, 404s (TableFilterViewController::assertVisibleInDomain).
Route::post('tables/{domain}/filter-views/{filterView}/favorite', [TableFilterViewController::class, 'favorite'])
    ->scopeBindings();
Route::delete('tables/{domain}/filter-views/{filterView}/favorite', [TableFilterViewController::class, 'unfavorite'])
    ->scopeBindings();
