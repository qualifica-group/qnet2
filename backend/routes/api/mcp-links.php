<?php

/*
|--------------------------------------------------------------------------
| MCP link module
|--------------------------------------------------------------------------
|
| Registered by bootstrap/app.php under the `api` prefix with the `api`,
| `auth:sanctum` and EnsureApiClientKey middleware: the three routes accept
| only the API client key, never a user token.
|
*/

use App\Http\Controllers\McpLinks\McpLinkController;
use Illuminate\Support\Facades\Route;

Route::post('mcp/links', [McpLinkController::class, 'open']);
Route::post('mcp/links/exchange', [McpLinkController::class, 'exchange']);
Route::post('mcp/links/{mcpLink}/revoke', [McpLinkController::class, 'revoke'])->whereNumber('mcpLink');
