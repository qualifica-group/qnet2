<?php

use App\Http\Controllers\CustomFields\CustomFieldController;
use App\Http\Controllers\CustomFields\CustomFieldEntitiesController;
use App\Http\Controllers\CustomFields\CustomFieldRelationTargetsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Custom field definitions CRUD (spec 0021, "ADMIN CRUD DEFINIZIONI")
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6): the
| admin catalogue of universal custom fields (App\CustomFields) grafted onto
| every custom-fieldable module via the TableRegistry/AuthorizationRegistry
| decorators. `entities` is declared ABOVE the plain `{customField}` show
| route so its literal segment wins over the bound wildcard, mirroring the
| for-select precedent. Authorization (custom-fields.view/create/update/
| delete) is enforced server-side in CustomFieldController via
| CustomFieldDefinitionPolicy on every endpoint. Required from routes/api.php
| INSIDE the existing `auth:sanctum` group, so every route below inherits
| that same middleware/prefix context.
*/

Route::get('custom-fields/entities', CustomFieldEntitiesController::class);
Route::get('custom-fields/relation-targets', CustomFieldRelationTargetsController::class);

Route::get('custom-fields/{customField}', [CustomFieldController::class, 'show']);
Route::post('custom-fields', [CustomFieldController::class, 'store']);
Route::match(['put', 'patch'], 'custom-fields/{customField}', [CustomFieldController::class, 'update']);
Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy']);
