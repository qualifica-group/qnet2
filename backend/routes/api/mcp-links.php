<?php

/*
|--------------------------------------------------------------------------
| MCP link module
|--------------------------------------------------------------------------
|
| Registered by McpLinksServiceProvider under the `api` prefix with the `api`,
| `auth:sanctum` and EnsureApiClientKey middleware: the three routes accept
| only the API client key, never a user token.
|
*/

use App\Http\Controllers\McpLinks\McpLinkController;
use Illuminate\Support\Facades\Route;

Route::post('mcp/links', [McpLinkController::class, 'open']);
Route::post('mcp/links/exchange', [McpLinkController::class, 'exchange']);
Route::delete('mcp/links/{mcpLink}', [McpLinkController::class, 'revoke'])->whereNumber('mcpLink');
