<?php

use App\Http\Controllers\Geo\GeoController;
use App\Http\Controllers\Geo\ProvinceForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Geo reference lookups (ADR 0010)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already at the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| Powers the address country → state → province → city cascade selects.
| Read-only reference data (Country / State / Province / City): no Policy, no
| per-resource permission — the only gate is auth:sanctum. states/provinces/
| cities require their parent filter (country_id / state_id /
| province_id|state_id) → 422 if absent. Each endpoint is a direct, bounded
| Eloquent read (documented exception: no Service, no business logic — see
| GeoController).
*/

Route::get('countries', [GeoController::class, 'countries']);
Route::get('states', [GeoController::class, 'states']);
Route::get('provinces', [GeoController::class, 'provinces']);
// Free-search province picker of a RELATION field targeting provinces (spec 0225).
Route::get('provinces/for-select', ProvinceForSelectController::class);
Route::get('cities', [GeoController::class, 'cities']);
