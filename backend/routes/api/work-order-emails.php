<?php

use App\Http\Controllers\WorkOrderEmails\WorkOrderEmailAttachmentController;
use App\Http\Controllers\WorkOrderEmails\WorkOrderEmailComposeContextController;
use App\Http\Controllers\WorkOrderEmails\WorkOrderEmailController;
use App\Http\Controllers\WorkOrderEmails\WorkOrderEmailTemplateRenderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order Emails (spec 0175)
|--------------------------------------------------------------------------
|
| The Commessa email composer/history: bozze, allegati, invio, storico.
| Nested under work-orders/{workOrder}/emails/{email} — every {email} route
| below carries `->scopeBindings()` (constraints, spec 0175), mirroring
| work-order-task-board.php's own {stage} routes. Required from
| routes/api/work-orders.php (file-size split, engineering.md §6) so every
| route below inherits the SAME `auth:sanctum` context that file already
| established.
|
| UNLIKE {stage} (WorkOrder::stages(), a real relation matching Laravel's
| naming convention), WorkOrder has no `emails()` method — its own relation
| is `outboundEmails()` (spec 0093/0175 naming: `outbound_emails` is the
| table, `emails` is only the URI's public word) — adding one would touch
| WorkOrder.php, outside this microtask's write surface. `{email}`/
| `{attachment}` are therefore left as plain scalar route parameters (never
| type-hinted to an Eloquent model in the controllers below), so Laravel's
| implicit binding never attempts `$workOrder->emails()` at all — the
| `->scopeBindings()` calls stay harmless/inert. The REAL "belongs to this
| commessa, visible to this actor" 404 enforcement is
| OutboundEmailService::resolveVisibleOrFail() /
| OutboundEmailAttachmentService::resolveOwnedOrFail(), called explicitly by
| every controller action below — same two-layer shape as imports.php's own
| `{row}: scopeBindings() + explicit assertRowBelongsToRun 404 guard`.
|
| Authorization is enforced server-side in the controllers via
| WorkOrderPolicy::viewEmails()/sendEmail() on every endpoint (D-14), never
| here. Literal `compose-context`/`render-template` segments are declared
| ABOVE the `{email}` wildcard routes so they are never captured as an email
| id (mirrors work-orders/next-code).
*/

Route::get('work-orders/{workOrder}/emails/compose-context', WorkOrderEmailComposeContextController::class);
Route::post('work-orders/{workOrder}/emails/render-template', WorkOrderEmailTemplateRenderController::class);

Route::get('work-orders/{workOrder}/emails', [WorkOrderEmailController::class, 'index']);
Route::post('work-orders/{workOrder}/emails', [WorkOrderEmailController::class, 'store']);

Route::get('work-orders/{workOrder}/emails/{email}', [WorkOrderEmailController::class, 'show'])
    ->whereNumber('email')->scopeBindings();
Route::match(['put', 'patch'], 'work-orders/{workOrder}/emails/{email}', [WorkOrderEmailController::class, 'update'])
    ->whereNumber('email')->scopeBindings();
Route::delete('work-orders/{workOrder}/emails/{email}', [WorkOrderEmailController::class, 'destroy'])
    ->whereNumber('email')->scopeBindings();
Route::post('work-orders/{workOrder}/emails/{email}/send', [WorkOrderEmailController::class, 'send'])
    ->whereNumber('email')->scopeBindings();

Route::post('work-orders/{workOrder}/emails/{email}/attachments', [WorkOrderEmailAttachmentController::class, 'store'])
    ->whereNumber('email')->scopeBindings();
Route::post('work-orders/{workOrder}/emails/{email}/attachments/import', [WorkOrderEmailAttachmentController::class, 'import'])
    ->whereNumber('email')->scopeBindings();
Route::delete('work-orders/{workOrder}/emails/{email}/attachments/{attachment}', [WorkOrderEmailAttachmentController::class, 'destroy'])
    ->whereNumber('email')->whereNumber('attachment')->scopeBindings();
Route::get('work-orders/{workOrder}/emails/{email}/attachments/{attachment}/download', [WorkOrderEmailAttachmentController::class, 'download'])
    ->whereNumber('email')->whereNumber('attachment')->scopeBindings();
