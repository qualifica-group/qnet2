<?php

use App\Http\Controllers\SupportRequests\SupportRequestController;
use App\Http\Controllers\SupportTopics\SupportTopicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IT support: "Tipi di problema" configuration + user requests (spec 0334)
|--------------------------------------------------------------------------
|
| Required from routes/api.php INSIDE the `auth:sanctum` group. The CRUD is
| gated server-side by SupportTopicPolicy (support-topics.*). The
| `support-requests` endpoints are open to ANY authenticated user (D-7): no
| permission and no throttle (backend.md §2).
*/

Route::get('support-topics/{supportTopic}', [SupportTopicController::class, 'show']);
Route::post('support-topics', [SupportTopicController::class, 'store']);
Route::match(['put', 'patch'], 'support-topics/{supportTopic}', [SupportTopicController::class, 'update']);
Route::delete('support-topics/{supportTopic}', [SupportTopicController::class, 'destroy']);

Route::get('support-requests/topics', [SupportRequestController::class, 'topics']);
Route::post('support-requests', [SupportRequestController::class, 'store']);
