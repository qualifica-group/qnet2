<?php

use App\Http\Controllers\Invoices\InvoiceCollectionController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\InvoiceInstallmentController;
use App\Http\Controllers\Invoices\InvoiceIssueController;
use App\Http\Controllers\Invoices\InvoicePdfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Active invoicing (spec 0194)
|--------------------------------------------------------------------------
|
| Authorization (invoices.* via InvoicePolicy, plus proforma-requests.view on
| the issue flow) is enforced server-side in the controllers on every
| endpoint. Required from routes/api.php INSIDE the `auth:sanctum` group. The
| list is the generic `invoices` table domain (no index route here). The
| literal segments (installment-preview, monthly-summary) are declared ABOVE
| the bound wildcard so they win.
*/

Route::get('proforma-requests/{proformaRequest}/invoice-draft', [InvoiceIssueController::class, 'draft']);
Route::post('proforma-requests/{proformaRequest}/invoice', [InvoiceIssueController::class, 'store']);

Route::post('invoices/installment-preview', [InvoiceController::class, 'installmentPreview']);
Route::get('invoices/monthly-summary', [InvoiceController::class, 'monthlySummary']);

Route::get('invoices/{invoice}/pdf', InvoicePdfController::class);
Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
Route::patch('invoices/{invoice}/details', [InvoiceController::class, 'updateDetails']);
Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);

// spec 0197: one installment (the installments module); the collection routes below stay as they are.
Route::get('invoice-installments/{installment}', [InvoiceInstallmentController::class, 'show']);
Route::patch('invoice-installments/{installment}', [InvoiceInstallmentController::class, 'update']);

Route::put('invoice-installments/{installment}/collection', [InvoiceCollectionController::class, 'store']);
Route::delete('invoice-installments/{installment}/collection', [InvoiceCollectionController::class, 'destroy']);
