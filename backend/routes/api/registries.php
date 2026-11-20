<?php

use App\Http\Controllers\Merge\RecordMergeController;
use App\Http\Controllers\Registries\RegistryController;
use App\Http\Controllers\Registries\RegistryForSelectController;
use App\Http\Controllers\Registries\RegistryLedgerController;
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

// Spec 0394: merge duplicates. Literal `merge` segments, declared ABOVE
// registries/{registry}; the entity key rides on the route default.
Route::post('registries/merge/preview', [RecordMergeController::class, 'preview'])->defaults('entity', 'registries');
Route::post('registries/merge', [RecordMergeController::class, 'merge'])->defaults('entity', 'registries');

Route::get('registries/{registry}', [RegistryController::class, 'show']);
// Spec 0266: Partitario of the registry; gated by `ledger.view` in the FormRequest.
Route::get('registries/{registry}/ledger', RegistryLedgerController::class)->whereNumber('registry');
Route::post('registries', [RegistryController::class, 'store']);
Route::match(['put', 'patch'], 'registries/{registry}', [RegistryController::class, 'update']);
Route::delete('registries/{registry}', [RegistryController::class, 'destroy']);
