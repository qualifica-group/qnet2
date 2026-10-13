<?php

use App\Http\Controllers\PurchaseRequestLineStatuses\PurchaseRequestLineStatusLabelController;
use App\Http\Controllers\PurchaseRequestLineStatuses\PurchaseRequestLineStatusLabelForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase request line status presentation catalogue (spec 0248)
|--------------------------------------------------------------------------
|
| System rows (one per PurchaseRequestLineStatus case): no create, no delete.
| Authorization (purchase-request-line-statuses.view/update) is enforced in
| PurchaseRequestLineStatusLabelController via PurchaseRequestLineStatusLabelPolicy.
| Required from routes/api/purchasing.php INSIDE the `auth:sanctum` group. The
| literal `for-select` and `reorder` segments sit ABOVE the bound wildcard.
*/

Route::get('purchase-request-line-statuses/for-select', PurchaseRequestLineStatusLabelForSelectController::class);
Route::post('purchase-request-line-statuses/reorder', [PurchaseRequestLineStatusLabelController::class, 'reorder']);
Route::get('purchase-request-line-statuses/{purchaseRequestLineStatus}', [PurchaseRequestLineStatusLabelController::class, 'show']);
Route::match(['put', 'patch'], 'purchase-request-line-statuses/{purchaseRequestLineStatus}', [PurchaseRequestLineStatusLabelController::class, 'update']);
