<?php

use App\Http\Controllers\Registries\RegistryController;
use App\Http\Controllers\Registries\RegistryForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registries CRUD (spec 0020, "Anagrafiche")
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6): a
| client/supplier record reusing the `users`/`referents` anagraphic stack
| (personal-data card + contacts + addresses) unchanged via HasPersonalData.
| for-select (spec 0023, ADR 0011): feeds the Project/Campaign form's
| "Anagrafica" select. Authorization (registries.view/create/update/delete)
| is enforced server-side in RegistryController via RegistryPolicy on every
| endpoint. Required from routes/api.php INSIDE the existing `auth:sanctum`
| group, so every route below inherits that same middleware/prefix context.
*/

// Minimal searchable/paginated registry list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE registries/{registry}
// so the literal `for-select` segment wins over the bound wildcard.
// The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('registries/for-select', RegistryForSelectController::class);

Route::get('registries/{registry}', [RegistryController::class, 'show']);
Route::post('registries', [RegistryController::class, 'store']);
Route::match(['put', 'patch'], 'registries/{registry}', [RegistryController::class, 'update']);
Route::delete('registries/{registry}', [RegistryController::class, 'destroy']);
