<?php

use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\ProductForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Products (spec 0017)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file sits at the 500-line hard limit). Required from routes/api.php
| INSIDE the existing `auth:sanctum` group, so every route below inherits
| that same middleware/prefix context.
|
| Authorization (products.view/create/update/delete, products.viewAny for the
| for-select) is enforced server-side in the controllers via ProductPolicy on
| every endpoint.
*/

// Minimal searchable/paginated product list for entity-backed selects
// (for-select standard, ADR 0011 — feeds the "prodotti di interesse" picker,
// optionally scoped by `category_ids[]`). Declared ABOVE products/{product}
// so the literal `for-select` segment wins over the bound wildcard,
// mirroring every other for-select precedent.
Route::get('products/for-select', ProductForSelectController::class);

// Next sequential code suggestion for the create form's auto-fill (spec
// 0065, D-1b). Declared ABOVE products/{product} so the literal `next-code`
// segment wins over the bound wildcard. Gated by products.create
// server-side in ProductController.
Route::get('products/next-code', [ProductController::class, 'nextCode']);

Route::get('products/{product}', [ProductController::class, 'show']);
Route::post('products', [ProductController::class, 'store']);
Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);
