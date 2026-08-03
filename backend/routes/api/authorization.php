<?php

use App\Http\Controllers\Authorization\FieldCatalogueController;
use App\Http\Controllers\Authorization\PermissionCatalogueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Role form authorization catalogues (specs 0006, 0076)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6).
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route below inherits that same middleware/prefix context.
|
| - `authorization/fields`: the static fields() of every registered resource
|   (spec 0006), feeding the Role form's field-permission matrix section.
|   Authorization (roles.create OR roles.update) is enforced server-side in
|   FieldCatalogueController.
| - `authorization/permission-catalogue`: the Area > Module tree (spec 0076)
|   for the two-panel permission explorer — assignable permissions + fields
|   (native/custom) grouped by the config/navigation.php taxonomy.
|   Authorization (roles.viewAny OR roles.create OR roles.update) is enforced
|   server-side in PermissionCatalogueController.
*/

Route::get('authorization/fields', [FieldCatalogueController::class, 'index']);
Route::get('authorization/permission-catalogue', [PermissionCatalogueController::class, 'index']);
