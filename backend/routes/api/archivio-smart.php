<?php

use App\Http\Controllers\ArchivioSmart\ArchivioSmartSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ArchivioSmart connection (spec 0403, D-2)
|--------------------------------------------------------------------------
|
| Single base URL + token used by the document checklists sync. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group. No throttle
| (backend.md section 2). Authorization: document-checklists.manageConnection,
| enforced in the controller.
*/

Route::get('archivio-smart/settings', [ArchivioSmartSettingsController::class, 'show']);
Route::put('archivio-smart/settings', [ArchivioSmartSettingsController::class, 'update']);
Route::post('archivio-smart/settings/test', [ArchivioSmartSettingsController::class, 'test']);

// Document requests of the work orders (spec 0428) go through the same connection.
require __DIR__.'/document-requests.php';
