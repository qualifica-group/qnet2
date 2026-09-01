<?php

use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Campaigns\CampaignForSelectController;
use App\Http\Controllers\Geo\StateForSelectController;
use App\Http\Controllers\PipelineStatuses\PipelineStatusController;
use App\Http\Controllers\PipelineStatuses\PipelineStatusForSelectController;
use App\Http\Controllers\ProductCategories\ProductCategoryBranchForSelectController;
use App\Http\Controllers\ProductCategories\ProductCategoryForSelectController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectForSelectController;
use App\Http\Controllers\Projects\ProjectSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Projects / Campaigns / Project statuses (spec 0023)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already at the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| Also carries product-categories/for-select and states/for-select: both are
| lookups introduced BY this same spec (0023) for the Project/Campaign forms;
| their host blocks (product-categories CRUD, geo lookups) already live in
| routes/api.php and had no line budget left. Registering the literal
| for-select route here — since this file is required BEFORE those blocks
| further down routes/api.php — still guarantees it wins over their bound
| wildcard, exactly as if declared inline.
*/

// Project statuses CRUD (BR-4 delete-guard lives in PipelineStatusService).
// Authorization (pipeline-statuses.view/create/update/delete) is enforced
// server-side in PipelineStatusController via PipelineStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE pipeline-statuses/{pipelineStatus} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('pipeline-statuses/for-select', PipelineStatusForSelectController::class);

// Custom-row resequencing (spec 0039, D-5): `sort_order` is server-managed,
// this is the only way to change it. Declared ABOVE the bound wildcard for
// the same literal-segment reason as `for-select`. Gated on
// pipeline-statuses.update directly in PipelineStatusController::reorder.
Route::post('pipeline-statuses/reorder', [PipelineStatusController::class, 'reorder']);

Route::get('pipeline-statuses/{pipelineStatus}', [PipelineStatusController::class, 'show']);
Route::post('pipeline-statuses', [PipelineStatusController::class, 'store']);
Route::match(['put', 'patch'], 'pipeline-statuses/{pipelineStatus}', [PipelineStatusController::class, 'update']);
Route::delete('pipeline-statuses/{pipelineStatus}', [PipelineStatusController::class, 'destroy']);

// Projects CRUD (BR-1 code generation, BR-5 delete-guard, BR-7 budget
// aggregates all live in ProjectService). Authorization (projects.view/
// create/update/delete) is enforced server-side in ProjectController via
// ProjectPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011),
// carrying the Campaign form's default `meta`. Declared ABOVE
// projects/{project} so the literal `for-select` segment wins over the
// bound wildcard. The only gate is auth:sanctum (ADR 0011, amended
// 2026-07-31).
Route::get('projects/for-select', ProjectForSelectController::class);

// KPI tiles for the card grid (spec 0025, D-3). Declared ABOVE
// projects/{project} so the literal `summary` segment wins over the
// bound wildcard. The only gate is auth:sanctum (ADR 0011, amended
// 2026-07-31).
Route::get('projects/summary', ProjectSummaryController::class);

// Next sequential code suggestion for the create form's auto-fill (spec
// 0025). Declared ABOVE projects/{project} so the literal `next-code`
// segment wins over the bound wildcard. Gated by projects.create
// server-side in ProjectController.
Route::get('projects/next-code', [ProjectController::class, 'nextCode']);

// Card-grid list (spec 0025, D-3): a plain index, distinct from the
// table framework — the card payload differs from the table row
// payload. The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('projects', [ProjectController::class, 'index']);

Route::get('projects/{project}', [ProjectController::class, 'show']);
Route::post('projects', [ProjectController::class, 'store']);
Route::match(['put', 'patch'], 'projects/{project}', [ProjectController::class, 'update']);
Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

// Campaigns CRUD (BR-1 code generation, BR-2 classification derivation, BR-3
// budget guard all live in CampaignService). Authorization (campaigns.view/
// create/update/delete) is enforced server-side in CampaignController via
// CampaignPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011,
// spec 0024 — feeds the Lead form's campaign field). Declared ABOVE
// campaigns/{campaign} so the literal `for-select` segment wins over the
// bound wildcard. The only gate is auth:sanctum (ADR 0011, amended
// 2026-07-31).
Route::get('campaigns/for-select', CampaignForSelectController::class);

// Next sequential code suggestion for the create form's auto-fill (spec
// 0025). Declared ABOVE campaigns/{campaign} so the literal `next-code`
// segment wins over the bound wildcard. Gated by campaigns.create
// server-side in CampaignController.
Route::get('campaigns/next-code', [CampaignController::class, 'nextCode']);

Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
Route::post('campaigns', [CampaignController::class, 'store']);
Route::match(['put', 'patch'], 'campaigns/{campaign}', [CampaignController::class, 'update']);
Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy']);

// Product categories / states for-select (spec 0023) — see file docblock for
// why they are declared here instead of inline in their own blocks.
Route::get('product-categories/for-select', ProductCategoryForSelectController::class);
// Branch picker of the quote-workflow criteria editor (spec 0092 D-4): a
// SEPARATE resource segment, so the sibling above keeps its unconditional
// `is_selectable` filter (spec 0074 D-4) untouched.
Route::get('product-category-branches/for-select', ProductCategoryBranchForSelectController::class);
Route::get('states/for-select', StateForSelectController::class);
