<?php

use App\Http\Controllers\DocumentChecklistCategories\DocumentChecklistCategoryController;
use App\Http\Controllers\DocumentChecklistCategories\DocumentChecklistCategoryForSelectController;
use App\Http\Controllers\DocumentChecklistCategories\DocumentChecklistCategorySyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document checklist categories / "Categorie checklist" (spec 0410)
|--------------------------------------------------------------------------
|
| CRUD, manual ArchivioSmart sync and sync log. The list is served by
| POST /api/tables/document-checklist-categories/rows. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group. No throttle
| (backend.md section 2). Authorization is enforced in the controllers via
| DocumentChecklistCategoryPolicy, which reuses the document-checklists.*
| permissions (D-2).
|
| `for-select` is declared ABOVE the {documentChecklistCategory} wildcard routes
| so its literal segment is never taken for an id; its only gate is auth:sanctum
| (ADR 0011). Soft-deleted categories resolve to 404 through the default route
| model binding.
*/

Route::get('document-checklist-categories/for-select', DocumentChecklistCategoryForSelectController::class);

Route::post('document-checklist-categories', [DocumentChecklistCategoryController::class, 'store']);
Route::get('document-checklist-categories/{documentChecklistCategory}', [DocumentChecklistCategoryController::class, 'show']);
Route::put('document-checklist-categories/{documentChecklistCategory}', [DocumentChecklistCategoryController::class, 'update']);
Route::delete('document-checklist-categories/{documentChecklistCategory}', [DocumentChecklistCategoryController::class, 'destroy']);

Route::post('document-checklist-categories/{documentChecklistCategory}/sync', [DocumentChecklistCategorySyncController::class, 'sync']);
Route::get('document-checklist-categories/{documentChecklistCategory}/sync-logs', [DocumentChecklistCategorySyncController::class, 'logs']);
