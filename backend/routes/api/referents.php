<?php

use App\Http\Controllers\Referents\ReferentController;
use App\Http\Controllers\Referents\ReferentForSelectController;
use App\Http\Controllers\Referents\ReferentRewardsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Referents CRUD (spec 0016)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file was already near the 500-line hard limit): a contact person/
| entity reusing the `users` anagraphic stack (personal-data card + contacts
| + addresses) unchanged via HasPersonalData. Authorization
| (referents.view/create/update/delete) is enforced server-side in
| ReferentController via ReferentPolicy on every endpoint. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
*/

// Minimal searchable/paginated referent list for entity-backed selects
// (for-select standard, ADR 0011, spec 0020 — first producer: the
// Registries form). Declared ABOVE referents/{referent} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('referents/for-select', ReferentForSelectController::class);

Route::get('referents/{referent}', [ReferentController::class, 'show']);
Route::post('referents', [ReferentController::class, 'store']);
Route::match(['put', 'patch'], 'referents/{referent}', [ReferentController::class, 'update']);
Route::delete('referents/{referent}', [ReferentController::class, 'destroy']);

// Lazy detail endpoint feeding the `rewarded-referents` AG Grid master/
// detail (spec 0059, D-4). An extra path segment beyond the bound
// `referents/{referent}` wildcard above, so it needs no reordering (Laravel
// resolves by segment count first) — still declared after the CRUD block to
// read naturally as "the referent's own sub-resources". Gated by this
// module's OWN `rewarded-referents.view` permission, never `referents.view`
// (independent permission sets, precedent request-management).
Route::get('referents/{referent}/rewards', ReferentRewardsController::class);
