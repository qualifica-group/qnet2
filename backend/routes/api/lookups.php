<?php

use App\Http\Controllers\OpportunityStatuses\OpportunityStatusController;
use App\Http\Controllers\OpportunityStatuses\OpportunityStatusForSelectController;
use App\Http\Controllers\OpportunityWorkflows\OpportunityWorkflowController;
use App\Http\Controllers\QuoteStatuses\QuoteStatusController;
use App\Http\Controllers\QuoteStatuses\QuoteStatusForSelectController;
use App\Http\Controllers\RewardStatuses\RewardStatusController;
use App\Http\Controllers\RewardStatuses\RewardStatusForSelectController;
use App\Http\Controllers\RewardTypes\RewardTypeController;
use App\Http\Controllers\RewardTypes\RewardTypeForSelectController;
use App\Http\Controllers\Sectors\SectorController;
use App\Http\Controllers\Sectors\SectorForSelectController;
use App\Http\Controllers\Sources\SourceController;
use App\Http\Controllers\Sources\SourceForSelectController;
use App\Http\Controllers\Tags\TagController;
use App\Http\Controllers\Tags\TagForSelectController;
use App\Http\Controllers\VatRates\VatRateController;
use App\Http\Controllers\VatRates\VatRateForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Standalone lookup-table routes
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6):
| Sources (spec 0018), Tags (spec 0019), Sectors (spec 0018) and Lead
| statuses (spec 0029) are all thin CRUD (+ for-select where applicable)
| wrappers over the generic table/authorization framework, sharing no state
| with the rest of the API. Required from routes/api.php INSIDE the existing
| `auth:sanctum` group, so every route below inherits that same
| middleware/prefix context.
*/

// Sources CRUD (spec 0018): a standalone lookup used to classify the
// provenance of registry records ("Anagrafiche"). Authorization
// (sources.view/create/update/delete) is enforced server-side in
// SourceController via SourcePolicy on every endpoint.
// Minimal searchable/paginated source list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE sources/{source} so
// the literal `for-select` segment wins over the bound wildcard.
// Gated by sources.viewAny server-side in SourceForSelectController.
Route::get('sources/for-select', SourceForSelectController::class);

Route::get('sources/{source}', [SourceController::class, 'show']);
Route::post('sources', [SourceController::class, 'store']);
Route::match(['put', 'patch'], 'sources/{source}', [SourceController::class, 'update']);
Route::delete('sources/{source}', [SourceController::class, 'destroy']);

// Tags CRUD (spec 0019): a reusable, polymorphic lookup attached to any
// entity via the `taggables` pivot (first producer: Referents).
// Authorization (tags.view/create/update/delete) is enforced
// server-side in TagController via TagPolicy on every endpoint.
// Minimal searchable/paginated tag list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE tags/{tag} so the
// literal `for-select` segment wins over the bound wildcard. Gated
// by tags.viewAny server-side in TagForSelectController.
Route::get('tags/for-select', TagForSelectController::class);

Route::get('tags/{tag}', [TagController::class, 'show']);
Route::post('tags', [TagController::class, 'store']);
Route::match(['put', 'patch'], 'tags/{tag}', [TagController::class, 'update']);
Route::delete('tags/{tag}', [TagController::class, 'destroy']);

// Sectors: CRUD + the dedicated tree view (spec 0018) — a lookup used to
// classify Anagrafiche (spec 0020, "Settore EA / Competenze", multi via
// sector_registry). `tree` and `for-select` are declared ABOVE the plain
// `{sector}` show route so their literal segments win over the bound
// wildcard. Authorization (sectors.view/create/update/delete/viewAny) is
// enforced server-side in SectorController/SectorForSelectController via
// SectorPolicy.
Route::get('sectors/tree', [SectorController::class, 'tree']);

// Minimal searchable/paginated sector list for entity-backed selects
// (for-select standard, ADR 0011, spec 0020 — first producer: the
// Registries form). Gated by sectors.viewAny server-side in
// SectorForSelectController.
Route::get('sectors/for-select', SectorForSelectController::class);

Route::get('sectors/{sector}', [SectorController::class, 'show']);
Route::post('sectors', [SectorController::class, 'store']);
Route::match(['put', 'patch'], 'sectors/{sector}', [SectorController::class, 'update']);
Route::delete('sectors/{sector}', [SectorController::class, 'destroy']);

// Opportunity statuses CRUD (spec 0043): the Opportunity working-state
// pick-list (BR-2 delete-guard lives in OpportunityStatusService).
// Authorization (opportunity-statuses.view/create/update/delete) is
// enforced server-side in OpportunityStatusController via
// OpportunityStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE opportunity-statuses/{opportunityStatus} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// opportunity-statuses.viewAny server-side in
// OpportunityStatusForSelectController.
Route::get('opportunity-statuses/for-select', OpportunityStatusForSelectController::class);

// Custom-row resequencing (spec 0039, D-5): `sort_order` is server-managed,
// this is the only way to change it. Declared ABOVE the bound wildcard for
// the same literal-segment reason as `for-select`. Gated on
// opportunity-statuses.update directly in OpportunityStatusController::reorder.
Route::post('opportunity-statuses/reorder', [OpportunityStatusController::class, 'reorder']);

Route::get('opportunity-statuses/{opportunityStatus}', [OpportunityStatusController::class, 'show']);
Route::post('opportunity-statuses', [OpportunityStatusController::class, 'store']);
Route::match(['put', 'patch'], 'opportunity-statuses/{opportunityStatus}', [OpportunityStatusController::class, 'update']);
Route::delete('opportunity-statuses/{opportunityStatus}', [OpportunityStatusController::class, 'destroy']);

// Opportunity workflow configurator CRUD (spec 0047, Lane A): the working-
// state "stati di lavorazione" dimension, distinct from opportunity-statuses
// (sales pipeline). Authorization (opportunity-workflows.view/create/update/
// delete) is enforced server-side in OpportunityWorkflowController via
// OpportunityWorkflowPolicy. `criterion-fields`/`default-statuses` are
// declared ABOVE opportunity-workflows/{opportunityWorkflow} so their literal
// segments win over the bound wildcard.
Route::get('opportunity-workflows/criterion-fields', [OpportunityWorkflowController::class, 'criterionFields']);
Route::get('opportunity-workflows/default-statuses', [OpportunityWorkflowController::class, 'defaultStatuses']);
Route::put('opportunity-workflows/default-statuses', [OpportunityWorkflowController::class, 'updateDefaultStatuses']);

Route::get('opportunity-workflows/{opportunityWorkflow}', [OpportunityWorkflowController::class, 'show']);
Route::post('opportunity-workflows', [OpportunityWorkflowController::class, 'store']);
Route::match(['put', 'patch'], 'opportunity-workflows/{opportunityWorkflow}', [OpportunityWorkflowController::class, 'update']);
Route::delete('opportunity-workflows/{opportunityWorkflow}', [OpportunityWorkflowController::class, 'destroy']);

// Quote statuses CRUD (spec 0065): the Quote working-state pick-list, a
// plain clone of opportunity-statuses (delete-guard lives in
// QuoteStatusService). Authorization (quote-statuses.view/create/update/
// delete) is enforced server-side in QuoteStatusController via
// QuoteStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE quote-statuses/{quoteStatus} so the literal `for-select`
// segment wins over the bound wildcard. Gated by quote-statuses.viewAny
// server-side in QuoteStatusForSelectController.
Route::get('quote-statuses/for-select', QuoteStatusForSelectController::class);

// Custom-row resequencing: `sort_order` is server-managed, this is the only
// way to change it. Declared ABOVE the bound wildcard for the same
// literal-segment reason as `for-select`. Gated on quote-statuses.update
// directly in QuoteStatusController::reorder.
Route::post('quote-statuses/reorder', [QuoteStatusController::class, 'reorder']);

Route::get('quote-statuses/{quoteStatus}', [QuoteStatusController::class, 'show']);
Route::post('quote-statuses', [QuoteStatusController::class, 'store']);
Route::match(['put', 'patch'], 'quote-statuses/{quoteStatus}', [QuoteStatusController::class, 'update']);
Route::delete('quote-statuses/{quoteStatus}', [QuoteStatusController::class, 'destroy']);

// Reward types CRUD (spec 0058): a pure anagraphic (name/color) describing
// the TYPES of voucher/reward/incentive usable in the CRM (BR-3: no
// delete-guard, no entity references reward_types in this version).
// Authorization (reward-types.view/create/update/delete) is enforced
// server-side in RewardTypeController via RewardTypePolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011,
// D-7). Declared ABOVE reward-types/{rewardType} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// reward-types.viewAny server-side in RewardTypeForSelectController.
Route::get('reward-types/for-select', RewardTypeForSelectController::class);

Route::get('reward-types/{rewardType}', [RewardTypeController::class, 'show']);
Route::post('reward-types', [RewardTypeController::class, 'store']);
Route::match(['put', 'patch'], 'reward-types/{rewardType}', [RewardTypeController::class, 'update']);
Route::delete('reward-types/{rewardType}', [RewardTypeController::class, 'destroy']);

// Reward statuses CRUD (spec 0060): the STATE of an assigned reward
// (BR-4 delete-guard lives in RewardStatusService). Authorization
// (reward-statuses.view/create/update/delete) is enforced server-side in
// RewardStatusController via RewardStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011,
// BR-5). Declared ABOVE reward-statuses/{rewardStatus} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// reward-statuses.viewAny server-side in RewardStatusForSelectController.
Route::get('reward-statuses/for-select', RewardStatusForSelectController::class);

// Custom-row resequencing (D-3): `sort_order` is server-managed, this is the
// only way to change it. Declared ABOVE the bound wildcard for the same
// literal-segment reason as `for-select`. Gated on reward-statuses.update
// directly in RewardStatusController::reorder.
Route::post('reward-statuses/reorder', [RewardStatusController::class, 'reorder']);

Route::get('reward-statuses/{rewardStatus}', [RewardStatusController::class, 'show']);
Route::post('reward-statuses', [RewardStatusController::class, 'store']);
Route::match(['put', 'patch'], 'reward-statuses/{rewardStatus}', [RewardStatusController::class, 'update']);
Route::delete('reward-statuses/{rewardStatus}', [RewardStatusController::class, 'destroy']);

// VAT rates CRUD: a standalone lookup used to assign a VAT percentage to a
// Product. Authorization (vat-rates.view/create/update/delete) is enforced
// server-side in VatRateController via VatRatePolicy on every endpoint.
// Minimal searchable/paginated list for entity-backed selects (for-select
// standard, ADR 0011). Declared ABOVE vat-rates/{vatRate} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// vat-rates.viewAny server-side in VatRateForSelectController.
Route::get('vat-rates/for-select', VatRateForSelectController::class);

Route::get('vat-rates/{vatRate}', [VatRateController::class, 'show']);
Route::post('vat-rates', [VatRateController::class, 'store']);
Route::match(['put', 'patch'], 'vat-rates/{vatRate}', [VatRateController::class, 'update']);
Route::delete('vat-rates/{vatRate}', [VatRateController::class, 'destroy']);
