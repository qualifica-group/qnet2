<?php

use App\Http\Controllers\InvoiceTags\InvoiceTagController;
use App\Http\Controllers\InvoiceTags\InvoiceTagForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Invoice tags (spec 0247)
|--------------------------------------------------------------------------
|
| Lookup tagging the active invoices. Authorization
| (invoice-tags.view/create/update/delete) is enforced server-side in
| InvoiceTagController via InvoiceTagPolicy. Required from
| routes/api/accounting.php INSIDE the `auth:sanctum` group. The literal
| `for-select` and `reorder` segments are declared ABOVE the bound wildcard so
| they are not swallowed by it; `reorder` is gated on invoice-tags.update in
| the controller.
*/

Route::get('invoice-tags/for-select', InvoiceTagForSelectController::class);
Route::post('invoice-tags/reorder', [InvoiceTagController::class, 'reorder']);
Route::get('invoice-tags/{invoiceTag}', [InvoiceTagController::class, 'show']);
Route::post('invoice-tags', [InvoiceTagController::class, 'store']);
Route::match(['put', 'patch'], 'invoice-tags/{invoiceTag}', [InvoiceTagController::class, 'update']);
Route::delete('invoice-tags/{invoiceTag}', [InvoiceTagController::class, 'destroy']);
