<?php

use App\Http\Controllers\PurchaseOrderEmails\PurchaseOrderEmailAttachmentController;
use App\Http\Controllers\PurchaseOrderEmails\PurchaseOrderEmailComposeContextController;
use App\Http\Controllers\PurchaseOrderEmails\PurchaseOrderEmailController;
use App\Http\Controllers\PurchaseOrderEmails\PurchaseOrderEmailTemplateRenderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase Order Emails (spec 0210)
|--------------------------------------------------------------------------
|
| Same shape as invoice-emails.php (without the reminder), nested under
| purchase-orders/{purchaseOrder}/emails. Required from
| routes/api/purchasing.php so it inherits `auth:sanctum`. `{email}` and
| `{attachment}` are plain scalar parameters: the controllers resolve them
| through OutboundEmailService, which 404s a foreign or hidden email. Literal
| segments (compose-context, render-template) precede the `{email}` wildcard
| routes. Authorization: PurchaseOrderPolicy view (read) / send (write).
*/

Route::get('purchase-orders/{purchaseOrder}/emails/compose-context', PurchaseOrderEmailComposeContextController::class)->whereNumber('purchaseOrder');
Route::post('purchase-orders/{purchaseOrder}/emails/render-template', PurchaseOrderEmailTemplateRenderController::class)->whereNumber('purchaseOrder');

Route::get('purchase-orders/{purchaseOrder}/emails', [PurchaseOrderEmailController::class, 'index'])->whereNumber('purchaseOrder');
Route::post('purchase-orders/{purchaseOrder}/emails', [PurchaseOrderEmailController::class, 'store'])->whereNumber('purchaseOrder');

Route::get('purchase-orders/{purchaseOrder}/emails/{email}', [PurchaseOrderEmailController::class, 'show'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();
Route::match(['put', 'patch'], 'purchase-orders/{purchaseOrder}/emails/{email}', [PurchaseOrderEmailController::class, 'update'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();
Route::delete('purchase-orders/{purchaseOrder}/emails/{email}', [PurchaseOrderEmailController::class, 'destroy'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();
Route::post('purchase-orders/{purchaseOrder}/emails/{email}/send', [PurchaseOrderEmailController::class, 'send'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();

Route::post('purchase-orders/{purchaseOrder}/emails/{email}/attachments', [PurchaseOrderEmailAttachmentController::class, 'store'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();
Route::post('purchase-orders/{purchaseOrder}/emails/{email}/attachments/import', [PurchaseOrderEmailAttachmentController::class, 'import'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->scopeBindings();
Route::delete('purchase-orders/{purchaseOrder}/emails/{email}/attachments/{attachment}', [PurchaseOrderEmailAttachmentController::class, 'destroy'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->whereNumber('attachment')->scopeBindings();
Route::get('purchase-orders/{purchaseOrder}/emails/{email}/attachments/{attachment}/download', [PurchaseOrderEmailAttachmentController::class, 'download'])
    ->whereNumber('purchaseOrder')->whereNumber('email')->whereNumber('attachment')->scopeBindings();
