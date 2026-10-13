<?php

use App\Http\Controllers\PurchaseInvoices\PurchaseInvoiceBulkPaymentController;
use App\Http\Controllers\PurchaseInvoices\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseInvoices\PurchaseInvoiceDraftController;
use App\Http\Controllers\PurchaseInvoices\PurchaseInvoiceInstallmentController;
use App\Http\Controllers\PurchaseInvoices\PurchaseInvoiceInvoiceableOrdersController;
use App\Http\Controllers\PurchaseInvoices\PurchaseInvoicePaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payable documents (spec 0250)
|--------------------------------------------------------------------------
|
| purchase-invoices.* via PurchaseInvoicePolicy, enforced server-side in the
| controllers. The list is the generic `purchase-invoices` table domain. The
| literal segments (draft, invoiceable-orders, installment-preview) are
| declared ABOVE the bound wildcard so they win.
*/

Route::get('purchase-invoices/draft', PurchaseInvoiceDraftController::class);
Route::get('purchase-invoices/invoiceable-orders', PurchaseInvoiceInvoiceableOrdersController::class);
Route::post('purchase-invoices/installment-preview', [PurchaseInvoiceController::class, 'installmentPreview']);
Route::post('purchase-invoices', [PurchaseInvoiceController::class, 'store']);
Route::get('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->whereNumber('purchaseInvoice');
Route::put('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->whereNumber('purchaseInvoice');
Route::patch('purchase-invoices/{purchaseInvoice}/details', [PurchaseInvoiceController::class, 'updateDetails'])->whereNumber('purchaseInvoice');
Route::delete('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->whereNumber('purchaseInvoice');

/*
|--------------------------------------------------------------------------
| Payable installments (spec 0211)
|--------------------------------------------------------------------------
|
| Authorization (purchase-invoice-installments.* and purchase-invoices.pay via
| their policies) is enforced server-side on every endpoint. Required from
| routes/api/purchasing.php, i.e. INSIDE the `auth:sanctum` group. The list is
| the generic `purchase-invoice-installments` table domain (no index route
| here). The literal `payments` segment is declared ABOVE the bound wildcard so
| it wins.
*/

Route::post('purchase-invoice-installments/payments', PurchaseInvoiceBulkPaymentController::class);
Route::get('purchase-invoice-installments/{installment}', [PurchaseInvoiceInstallmentController::class, 'show']);
Route::patch('purchase-invoice-installments/{installment}', [PurchaseInvoiceInstallmentController::class, 'update']);
Route::put('purchase-invoice-installments/{installment}/payment', [PurchaseInvoicePaymentController::class, 'store']);
Route::delete('purchase-invoice-installments/{installment}/payment', [PurchaseInvoicePaymentController::class, 'destroy']);
