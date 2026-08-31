<?php

use App\Http\Controllers\Quotes\QuoteCommissionDefaultsController;
use App\Http\Controllers\Quotes\QuoteCommissionRecipientsController;
use App\Http\Controllers\Quotes\QuoteController;
use App\Http\Controllers\Quotes\QuoteDocumentController;
use App\Http\Controllers\Quotes\QuoteForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quotes (spec 0065, MT-05)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/opportunities.php. Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| next-code (D-13): declared ABOVE quotes/{quote} so the literal segment wins
| over the route-model-binding wildcard (mirrors projects/next-code,
| campaigns/next-code, products/next-code).
*/

Route::get('quotes/next-code', [QuoteController::class, 'nextCode']);
// for-select (spec 0059 amendment A-01): feeds the `rewarded-referents`
// "Offerta" advanced filter. Declared ABOVE quotes/{quote} for the same
// literal-segment-wins reason as next-code.
Route::get('quotes/for-select', QuoteForSelectController::class);
Route::post('quotes/commission-defaults', QuoteCommissionDefaultsController::class);
Route::post('quotes/commission-recipients', QuoteCommissionRecipientsController::class);
// Spec 0084, D-5: live preview of the dynamic "Informazioni aggiuntive" the
// composed offer lines resolve to, for the CREATE form — declared ABOVE
// quotes/{quote} so the literal segment wins over the route-model-binding
// wildcard (mirrors next-code above).
Route::post('quotes/form-context', [QuoteController::class, 'formContext']);

// Quotes CRUD. Authorization (quotes.view/create/update/delete) is enforced
// server-side in QuoteController via QuotePolicy on every endpoint.
Route::get('quotes/{quote}', [QuoteController::class, 'show']);
Route::post('quotes', [QuoteController::class, 'store']);
Route::match(['put', 'patch'], 'quotes/{quote}', [QuoteController::class, 'update']);
Route::delete('quotes/{quote}', [QuoteController::class, 'destroy']);

// `.docx` generation (spec 0070, D-2): a pure read (quotes.view), generated
// synchronously and streamed back, never persisted. POST (not GET): it
// produces an artifact, mirroring `POST /api/exports/{domain}`.
Route::post('quotes/{quote}/document', QuoteDocumentController::class);
