<?php

use App\Http\Controllers\InvoiceEmails\InvoiceEmailAttachmentController;
use App\Http\Controllers\InvoiceEmails\InvoiceEmailComposeContextController;
use App\Http\Controllers\InvoiceEmails\InvoiceEmailController;
use App\Http\Controllers\InvoiceEmails\InvoiceEmailReminderController;
use App\Http\Controllers\InvoiceEmails\InvoiceEmailTemplateRenderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Invoice Emails (spec 0195)
|--------------------------------------------------------------------------
|
| Same shape as work-order-emails.php, nested under invoices/{invoice}/emails.
| Required from routes/api/accounting.php so it inherits `auth:sanctum`.
| `{email}`/`{attachment}` are plain scalar parameters: the controllers
| resolve them through OutboundEmailService, which 404s a foreign or hidden
| email. Literal segments (compose-context, render-template, reminder)
| precede the `{email}` wildcard routes. Authorization:
| InvoicePolicy::viewEmails()/sendEmail().
*/

Route::get('invoices/{invoice}/emails/compose-context', InvoiceEmailComposeContextController::class)->whereNumber('invoice');
Route::post('invoices/{invoice}/emails/render-template', InvoiceEmailTemplateRenderController::class)->whereNumber('invoice');
Route::post('invoices/{invoice}/emails/reminder', InvoiceEmailReminderController::class)->whereNumber('invoice');

Route::get('invoices/{invoice}/emails', [InvoiceEmailController::class, 'index'])->whereNumber('invoice');
Route::post('invoices/{invoice}/emails', [InvoiceEmailController::class, 'store'])->whereNumber('invoice');

Route::get('invoices/{invoice}/emails/{email}', [InvoiceEmailController::class, 'show'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();
Route::match(['put', 'patch'], 'invoices/{invoice}/emails/{email}', [InvoiceEmailController::class, 'update'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();
Route::delete('invoices/{invoice}/emails/{email}', [InvoiceEmailController::class, 'destroy'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();
Route::post('invoices/{invoice}/emails/{email}/send', [InvoiceEmailController::class, 'send'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();

Route::post('invoices/{invoice}/emails/{email}/attachments', [InvoiceEmailAttachmentController::class, 'store'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();
Route::post('invoices/{invoice}/emails/{email}/attachments/import', [InvoiceEmailAttachmentController::class, 'import'])
    ->whereNumber('invoice')->whereNumber('email')->scopeBindings();
Route::delete('invoices/{invoice}/emails/{email}/attachments/{attachment}', [InvoiceEmailAttachmentController::class, 'destroy'])
    ->whereNumber('invoice')->whereNumber('email')->whereNumber('attachment')->scopeBindings();
Route::get('invoices/{invoice}/emails/{email}/attachments/{attachment}/download', [InvoiceEmailAttachmentController::class, 'download'])
    ->whereNumber('invoice')->whereNumber('email')->whereNumber('attachment')->scopeBindings();
