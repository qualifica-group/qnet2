<?php

use App\Http\Controllers\DocumentLayouts\DocumentLayoutController;
use App\Http\Controllers\DocumentLayouts\DocumentLayoutForSelectController;
use App\Http\Controllers\DocumentLayouts\DocumentLayoutImageController;
use App\Http\Controllers\DocumentLayouts\DocumentLayoutPreviewController;
use App\Http\Controllers\DocumentLayouts\DocumentLayoutVariableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document layouts routes (spec 0069)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6),
| mirroring routes/api/lookups.php. Required from routes/api.php INSIDE the
| existing `auth:sanctum` group, so every route below inherits that same
| middleware/prefix context.
|
| No `Route::resource()`, no `index()`: the list is served generically by
| `GET /api/tables/document-layouts/rows`. `for-select`/`variables` and the
| nested `{documentLayout}/images*` routes are declared ABOVE the plain
| `{documentLayout}` show route so their literal segments win over the bound
| wildcard (same convention as every other lookup in this codebase).
|
| The nested images routes are NOT `scopeBindings()`-wrapped: DocumentLayout's
| own attachments relation is `images()` (scoped to the `layout_image`
| collection, wave 1), not the `attachments()` name Laravel's implicit
| nested-binding scoping conventionally resolves against — DocumentLayoutImageController
| verifies attachment ownership explicitly instead (404, not 403, per the
| data_contract's DELETE .../images/{attachment} — AC-094).
*/

// Minimal searchable/paginated document layout list for entity-backed
// selects (for-select standard, ADR 0011), scoped to a required `module`.
// The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('document-layouts/for-select', DocumentLayoutForSelectController::class);

// The variable catalogue (`{category.key}` tokens) for a `module`, masked by
// the actor's field permissions (D-6). Gated by document-layouts.viewAny
// server-side in DocumentLayoutVariableController (no dedicated permission).
Route::get('document-layouts/variables', DocumentLayoutVariableController::class);

// Layout images (logos / carta intestata): list/upload/delete. Gated by
// document-layouts.view (index) / document-layouts.update (store/destroy)
// server-side in DocumentLayoutImageController — currently a stub (see that
// class' docblock), implemented by this spec's image step (MT-4).
Route::get('document-layouts/{documentLayout}/images', [DocumentLayoutImageController::class, 'index']);
Route::post('document-layouts/{documentLayout}/images', [DocumentLayoutImageController::class, 'store']);
Route::delete('document-layouts/{documentLayout}/images/{attachment}', [DocumentLayoutImageController::class, 'destroy']);

// A real `.docx` preview of this layout's config (spec 0070), gated by
// document-layouts.view. Declared above the plain `{documentLayout}` show
// route only for grouping consistency with the other nested sub-routes above
// — no ordering ambiguity exists here (different HTTP verb AND an extra path
// segment already disambiguate it from the bound wildcard route).
Route::post('document-layouts/{documentLayout}/preview', DocumentLayoutPreviewController::class);

// Document layouts CRUD. Authorization (document-layouts.view/create/update/
// delete) is enforced server-side in DocumentLayoutController via
// DocumentLayoutPolicy.
Route::get('document-layouts/{documentLayout}', [DocumentLayoutController::class, 'show']);
Route::post('document-layouts', [DocumentLayoutController::class, 'store']);
Route::match(['put', 'patch'], 'document-layouts/{documentLayout}', [DocumentLayoutController::class, 'update']);
Route::delete('document-layouts/{documentLayout}', [DocumentLayoutController::class, 'destroy']);
