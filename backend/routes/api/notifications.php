<?php

use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notifications (spec 0150)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6),
| mirroring routes/api/contracts.php. Required from routes/api.php INSIDE
| the existing `auth:sanctum` group, so every route below inherits that
| same middleware/prefix context.
|
| In-app user notifications (Laravel native `database` channel). Every
| endpoint is self-scoped by construction to the authenticated user's own
| notifications (auth()->user()->notifications()), so a foreign/unknown id
| resolves to 404; authorization is ownership, not a Spatie permission or a
| Policy (see ADR-0005 / docs/api/0004-notifications.md). The literal
| `unread-count`/`read-all`/`bulk-read` segments are declared ABOVE the
| wildcard `{notification}/...` routes, mirroring the company-sites
| `for-select` precedent, so they never risk losing to the bound param.
*/

Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::post('notifications/bulk-read', [NotificationController::class, 'bulkMarkAsRead']);
Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
Route::patch('notifications/{notification}/unread', [NotificationController::class, 'markAsUnread']);
