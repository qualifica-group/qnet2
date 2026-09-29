<?php

use App\Http\Controllers\DocumentBundles\DocumentBundleController;
use App\Http\Controllers\DocumentBundles\DocumentBundleForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document Bundles / "Modelli documenti" (spec 0175)
|--------------------------------------------------------------------------
|
| CRUD + for-select. The files themselves pass through the existing
| /api/attachments endpoints (alias `document_bundle`, collection
| `documents`) — no dedicated upload route here. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| `for-select` is declared ABOVE document-bundles/{documentBundle} so its
| literal segment wins over the route-model-binding wildcard (same
| convention as every other lookup in this codebase).
*/

// Minimal searchable/paginated document bundle list feeding the Commessa
// email composer's "Da modello documenti" picker (for-select standard, ADR
// 0011). The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('document-bundles/for-select', DocumentBundleForSelectController::class);

// Document bundles CRUD. Authorization (document-bundles.view/create/update/
// delete) is enforced server-side in DocumentBundleController via
// DocumentBundlePolicy.
Route::get('document-bundles/{documentBundle}', [DocumentBundleController::class, 'show']);
Route::post('document-bundles', [DocumentBundleController::class, 'store']);
Route::match(['put', 'patch'], 'document-bundles/{documentBundle}', [DocumentBundleController::class, 'update']);
Route::delete('document-bundles/{documentBundle}', [DocumentBundleController::class, 'destroy']);
