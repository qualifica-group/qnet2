<?php

/*
|--------------------------------------------------------------------------
| Integration clients administration (spec 0209)
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php under the `api` prefix with the `api` and
| `auth:sanctum` middleware. Admin routes: actor User (authorised by
| ApiClientPolicy). client-login (spec 0210): authenticated by the client key.
|
*/

use App\Http\Controllers\ApiClients\ApiClientController;
use App\Http\Controllers\ApiClients\ApiDocsController;
use App\Http\Controllers\ApiClients\ClientLoginController;
use Illuminate\Support\Facades\Route;

// Login of a QNet user through the client key; credential endpoint, so throttled.
Route::post('auth/client-login', ClientLoginController::class)->middleware('throttle:6,1');

// Static segments are declared BEFORE the {apiClient} wildcard.
Route::get('api-clients/docs/openapi', [ApiDocsController::class, 'openapi']);
Route::get('api-clients/docs/postman', [ApiDocsController::class, 'postman']);

Route::post('api-clients/{apiClient}/rotate-key', [ApiClientController::class, 'rotateKey']);

Route::apiResource('api-clients', ApiClientController::class)
    ->parameters(['api-clients' => 'apiClient'])
    ->except(['index']);
