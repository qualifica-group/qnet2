<?php

use App\Http\Controllers\ContractStatuses\ContractStatusController;
use App\Http\Controllers\ContractStatuses\ContractStatusForSelectController;
use App\Http\Controllers\PaymentMethods\PaymentMethodController;
use App\Http\Controllers\PaymentMethods\PaymentMethodForSelectController;
use App\Http\Controllers\QuoteWorkflows\QuoteWorkflowController;
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
use App\Http\Controllers\UnitsOfMeasure\UnitOfMeasureController;
use App\Http\Controllers\UnitsOfMeasure\UnitOfMeasureForSelectController;
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
// The only gate is auth:sanctum (ADR 0011, amended 2026-07-31).
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
// literal `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
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
// Registries form). The only gate is auth:sanctum (ADR 0011, amended
// 2026-07-31).
Route::get('sectors/for-select', SectorForSelectController::class);

Route::get('sectors/{sector}', [SectorController::class, 'show']);
Route::post('sectors', [SectorController::class, 'store']);
Route::match(['put', 'patch'], 'sectors/{sector}', [SectorController::class, 'update']);
Route::delete('sectors/{sector}', [SectorController::class, 'destroy']);

// Quote workflow configurator CRUD (spec 0047, moved onto the Offerta by
// spec 0083 D-6): the working-state "stato dell'offerta" dimension.
// Authorization (quote-workflows.view/create/update/delete) is enforced
// server-side in QuoteWorkflowController via QuoteWorkflowPolicy.
// `criterion-fields`/`default-statuses` are declared ABOVE
// quote-workflows/{quoteWorkflow} so their literal segments win over the
// bound wildcard.
Route::get('quote-workflows/criterion-fields', [QuoteWorkflowController::class, 'criterionFields']);
Route::get('quote-workflows/default-statuses', [QuoteWorkflowController::class, 'defaultStatuses']);
Route::put('quote-workflows/default-statuses', [QuoteWorkflowController::class, 'updateDefaultStatuses']);

Route::get('quote-workflows/{quoteWorkflow}', [QuoteWorkflowController::class, 'show']);
Route::post('quote-workflows', [QuoteWorkflowController::class, 'store']);
Route::match(['put', 'patch'], 'quote-workflows/{quoteWorkflow}', [QuoteWorkflowController::class, 'update']);
Route::delete('quote-workflows/{quoteWorkflow}', [QuoteWorkflowController::class, 'destroy']);

// Reward types CRUD (spec 0058): a pure anagraphic (name/color) describing
// the TYPES of voucher/reward/incentive usable in the CRM (BR-3: no
// delete-guard, no entity references reward_types in this version).
// Authorization (reward-types.view/create/update/delete) is enforced
// server-side in RewardTypeController via RewardTypePolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011,
// D-7). Declared ABOVE reward-types/{rewardType} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
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
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
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

// Payment methods CRUD (spec 0068): a standalone lookup describing the
// payment modalities selectable across the CRM; `quotes` is its first
// consumer (user directive 2026-07-30, `quotes.payment_method_id`), which is
// why destroy() is now guarded against referencing quotes. Authorization
// (payment-methods.view/create/update/delete) is enforced server-side in
// PaymentMethodController via PaymentMethodPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE payment-methods/{paymentMethod} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('payment-methods/for-select', PaymentMethodForSelectController::class);

// Resequencing (D-1): `sort_order` is server-managed, this is the only way
// to change it. Declared ABOVE the bound wildcard for the same
// literal-segment reason as `for-select`. Gated on payment-methods.update
// directly in PaymentMethodController::reorder.
Route::post('payment-methods/reorder', [PaymentMethodController::class, 'reorder']);

Route::get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show']);
Route::post('payment-methods', [PaymentMethodController::class, 'store']);
Route::match(['put', 'patch'], 'payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

// VAT rates CRUD: a standalone lookup used to assign a VAT percentage to a
// Product. Authorization (vat-rates.view/create/update/delete) is enforced
// server-side in VatRateController via VatRatePolicy on every endpoint.
// Minimal searchable/paginated list for entity-backed selects (for-select
// standard, ADR 0011). Declared ABOVE vat-rates/{vatRate} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('vat-rates/for-select', VatRateForSelectController::class);

Route::get('vat-rates/{vatRate}', [VatRateController::class, 'show']);
Route::post('vat-rates', [VatRateController::class, 'store']);
Route::match(['put', 'patch'], 'vat-rates/{vatRate}', [VatRateController::class, 'update']);
Route::delete('vat-rates/{vatRate}', [VatRateController::class, 'destroy']);

// Units of measure CRUD (spec 0088): a standalone lookup used to classify a
// Product's quantity, congealed onto Quote lines at write time (D-5).
// Authorization (units-of-measure.view/create/update/delete) is enforced
// server-side in UnitOfMeasureController via UnitOfMeasurePolicy on every
// endpoint. Minimal searchable/paginated list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE units-of-measure/{unitOfMeasure}
// so the literal `for-select` segment wins over the bound wildcard. The only
// gate is auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('units-of-measure/for-select', UnitOfMeasureForSelectController::class);

Route::get('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'show']);
Route::post('units-of-measure', [UnitOfMeasureController::class, 'store']);
Route::match(['put', 'patch'], 'units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'update']);
Route::delete('units-of-measure/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy']);

// Contract statuses CRUD (spec 0072): the Contract working-state pick-list,
// combining reward-statuses' description/is_active shape with
// document-layouts' exclusive default (BR-5, delete-guard lives in
// ContractStatusService). Authorization (contract-statuses.view/create/
// update/delete) is enforced server-side in ContractStatusController via
// ContractStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE contract-statuses/{contractStatus} so the literal
// `for-select` segment wins over the bound wildcard. The only gate is
// auth:sanctum (ADR 0011, amended 2026-07-31).
Route::get('contract-statuses/for-select', ContractStatusForSelectController::class);

// Custom-row resequencing: `sort_order` is server-managed, this is the only
// way to change it. Declared ABOVE the bound wildcard for the same
// literal-segment reason as `for-select`. Gated on contract-statuses.update
// directly in ContractStatusController::reorder.
Route::post('contract-statuses/reorder', [ContractStatusController::class, 'reorder']);

Route::get('contract-statuses/{contractStatus}', [ContractStatusController::class, 'show']);
Route::post('contract-statuses', [ContractStatusController::class, 'store']);
Route::match(['put', 'patch'], 'contract-statuses/{contractStatus}', [ContractStatusController::class, 'update']);
Route::delete('contract-statuses/{contractStatus}', [ContractStatusController::class, 'destroy']);
