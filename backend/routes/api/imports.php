<?php

use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Import\ImportMappingTemplateController;
use App\Http\Controllers\Import\ImportValueMappingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Import domain routes
|--------------------------------------------------------------------------
|
| Extracted from routes/api.php (engineering.md §6, 500-line hard limit) —
| required from WITHIN that file's `auth:sanctum` middleware group, so every
| route below inherits it exactly as if inlined. Only the imports/{domain}
| endpoint family lives here; exports/migrations stay in the parent file.
|
| Generic, domain-driven CSV/XLSX import engine (spec 0012, extended by
| the wizard flow spec 0033): {domain} resolves the ImportDefinition
| (config/imports.php), unknown → 404. Authorization enforced server-side
| in ImportController; a bound {importRun} not owned by the actor OR
| whose resource != {domain} 404s.
| {row}: scopeBindings() + explicit assertRowBelongsToRun 404 guard.
*/
Route::get('imports/{domain}/template', [ImportController::class, 'template']);
Route::get('imports/{domain}', [ImportController::class, 'index']);

// Team-shared, per-domain saved column-mapping templates (spec 0035):
// list/create are DOUBLE-GATED exactly like the CSV template above;
// delete is owner-only via ImportMappingTemplatePolicy. Literal
// `mapping-templates` segment registered BEFORE the {importRun} wildcard
// below so it is never captured as a run id.
Route::get('imports/{domain}/mapping-templates', [ImportMappingTemplateController::class, 'index']);
Route::post('imports/{domain}/mapping-templates', [ImportMappingTemplateController::class, 'store']);
Route::delete('imports/{domain}/mapping-templates/{mappingTemplate}', [ImportMappingTemplateController::class, 'destroy'])
    ->scopeBindings();

Route::get('imports/{domain}/{importRun}', [ImportController::class, 'show']);
Route::get('imports/{domain}/{importRun}/summary', [ImportController::class, 'summary']);
Route::get('imports/{domain}/{importRun}/errors', [ImportController::class, 'errors']);
Route::post('imports/{domain}/{importRun}/rows', [ImportController::class, 'rows']);
// Literal `assign` segment registered BEFORE the {row} wildcard below,
// same precedent as `mapping-templates` above — otherwise {row} would
// shadow it and fail model binding on the literal "assign" id.
Route::patch('imports/{domain}/{importRun}/rows/assign', [ImportController::class, 'bulkAssign']);
Route::patch('imports/{domain}/{importRun}/rows/{row}', [ImportController::class, 'updateRow'])->scopeBindings();
Route::patch('imports/{domain}/{importRun}/rows/{row}/resolution', [ImportController::class, 'updateRowResolution'])->scopeBindings();

Route::post('imports/{domain}', [ImportController::class, 'upload']);
Route::put('imports/{domain}/{importRun}/configure', [ImportController::class, 'configure']);
Route::post('imports/{domain}/{importRun}/confirm', [ImportController::class, 'confirm']);

// Value-mapping step of the wizard (spec 0348): distinct file values of the
// mapped columns + the records they can be matched to. Domains without
// value_mapping_fields 404.
Route::post('imports/{domain}/{importRun}/value-mapping', [ImportValueMappingController::class, 'preview']);
