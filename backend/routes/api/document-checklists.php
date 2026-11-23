<?php

use App\Http\Controllers\DocumentChecklists\DocumentChecklistController;
use App\Http\Controllers\DocumentChecklists\DocumentChecklistSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document checklists / "Checklist documentali" (spec 0403)
|--------------------------------------------------------------------------
|
| CRUD, activation toggle, manual ArchivioSmart sync and sync log. The list is
| served by POST /api/tables/document-checklists/rows. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group. No throttle
| (backend.md section 2). Authorization (document-checklists.*) is enforced in
| the controllers via DocumentChecklistPolicy.
|
| `sync-all` is declared ABOVE the {documentChecklist} wildcard routes so its
| literal segment is never taken for an id. Soft-deleted checklists resolve to
| 404 through the default route model binding.
*/

Route::post('document-checklists/sync-all', [DocumentChecklistSyncController::class, 'syncAll']);

Route::post('document-checklists', [DocumentChecklistController::class, 'store']);
Route::get('document-checklists/{documentChecklist}', [DocumentChecklistController::class, 'show']);
Route::put('document-checklists/{documentChecklist}', [DocumentChecklistController::class, 'update']);
Route::patch('document-checklists/{documentChecklist}/activation', [DocumentChecklistController::class, 'activation']);
Route::delete('document-checklists/{documentChecklist}', [DocumentChecklistController::class, 'destroy']);

Route::post('document-checklists/{documentChecklist}/sync', [DocumentChecklistSyncController::class, 'sync']);
Route::get('document-checklists/{documentChecklist}/sync-logs', [DocumentChecklistSyncController::class, 'logs']);
