<?php

use App\Http\Controllers\Quotes\QuoteCommissionDefaultsController;
use App\Http\Controllers\Quotes\QuoteCommissionRecipientsController;
use App\Http\Controllers\Quotes\QuoteController;
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
Route::post('quotes/commission-defaults', QuoteCommissionDefaultsController::class);
Route::post('quotes/commission-recipients', QuoteCommissionRecipientsController::class);

// Quotes CRUD. Authorization (quotes.view/create/update/delete) is enforced
// server-side in QuoteController via QuotePolicy on every endpoint.
Route::get('quotes/{quote}', [QuoteController::class, 'show']);
Route::post('quotes', [QuoteController::class, 'store']);
Route::match(['put', 'patch'], 'quotes/{quote}', [QuoteController::class, 'update']);
Route::delete('quotes/{quote}', [QuoteController::class, 'destroy']);
