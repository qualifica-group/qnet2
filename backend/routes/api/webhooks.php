<?php

use App\Http\Controllers\Webhooks\ArchivioSmartWebhookController;
use App\Http\Middleware\VerifyArchivioSmartSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound webhooks (spec 0428)
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php under the `api` middleware and the /api
| prefix, WITHOUT auth:sanctum: the caller is authenticated by the HMAC
| signature of the body. No throttle (backend.md section 2).
*/

Route::post('webhooks/archivio-smart', ArchivioSmartWebhookController::class)
    ->middleware(VerifyArchivioSmartSignature::class)
    ->name('webhooks.archivio-smart');
