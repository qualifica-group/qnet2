<?php

use App\Http\Controllers\EmailTemplates\EmailTemplateController;
use App\Http\Controllers\EmailTemplates\EmailTemplateForSelectController;
use App\Http\Controllers\EmailTemplates\EmailTemplateVariableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Email Templates / "Modelli email" (spec 0175)
|--------------------------------------------------------------------------
|
| CRUD + for-select + variables catalogue + render-template preview. Required
| from routes/api.php INSIDE the existing `auth:sanctum` group, so every
| route below inherits that same middleware/prefix context.
|
| `for-select`/`variables` are declared ABOVE email-templates/{emailTemplate}
| so their literal segments win over the route-model-binding wildcard (same
| convention as every other lookup in this codebase).
*/

// Minimal searchable/paginated email template list for ONE `module`, feeding
// the Commessa email composer's template picker (for-select standard, ADR
// 0011). The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('email-templates/for-select', EmailTemplateForSelectController::class);

// The `{category.key}` variable catalogue for a `module` (D-4). Gated by
// email-templates.view server-side in EmailTemplateVariableController.
Route::get('email-templates/variables', EmailTemplateVariableController::class);

// Email templates CRUD. Authorization (email-templates.view/create/update/
// delete) is enforced server-side in EmailTemplateController via
// EmailTemplatePolicy.
Route::get('email-templates/{emailTemplate}', [EmailTemplateController::class, 'show']);
Route::post('email-templates', [EmailTemplateController::class, 'store']);
Route::match(['put', 'patch'], 'email-templates/{emailTemplate}', [EmailTemplateController::class, 'update']);
Route::delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy']);
