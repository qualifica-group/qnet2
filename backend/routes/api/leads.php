<?php

use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadForSelectController;
use App\Http\Controllers\Leads\LeadOpportunityDefaultsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leads (spec 0024)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already at the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
*/

// Minimal searchable/paginated lead list for entity-backed selects
// (amendment rev.1 A-1, ADR 0011) — feeds the Opportunity form's "Lead"
// select (spec 0040). Declared ABOVE leads/{lead} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// leads.viewAny server-side in LeadForSelectController.
Route::get('leads/for-select', LeadForSelectController::class);

// Opportunity BR-1 defaults for the "create opportunity from lead" form
// (spec 0040). Declared ABOVE the plain leads/{lead} show route so the
// literal `opportunity-defaults` segment wins over the bound wildcard —
// no conflict either way (differing segment count), but mirrors the
// for-select precedent. Double-gated (opportunities.create AND leads.view)
// in LeadOpportunityDefaultsController itself.
Route::get('leads/{lead}/opportunity-defaults', LeadOpportunityDefaultsController::class);

// Bulk-assign Operatore + Sede to many existing leads at once (spec 0048).
// POST-only (never a GET/PUT/PATCH/DELETE leads/{lead} collision, so
// placement relative to the wildcard below is safe either way). Gated
// per-lead by leads.update in LeadController::assignOperators.
Route::post('leads/assign-operators', [LeadController::class, 'assignOperators']);

// Bulk Lead -> Opportunity conversion, the leads table's mass action (spec
// 0071). POST-only, same placement rationale as assign-operators above.
// Double-gated in LeadController::convertToOpportunities: opportunities.create
// plus leads.view per targeted lead.
Route::post('leads/convert-to-opportunities', [LeadController::class, 'convertToOpportunities']);

// Leads CRUD. Authorization (leads.view/create/update/delete) is enforced
// server-side in LeadController via LeadPolicy on every endpoint.
Route::get('leads/{lead}', [LeadController::class, 'show']);
Route::post('leads', [LeadController::class, 'store']);
Route::match(['put', 'patch'], 'leads/{lead}', [LeadController::class, 'update']);
Route::delete('leads/{lead}', [LeadController::class, 'destroy']);
