<?php

namespace App\Providers;

use App\Listeners\McpLinks\DropMcpLinksOnCredentialChange;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring of the MCP link module: the credential-change listener. The routes are
 * registered in bootstrap/app.php, like the API clients ones.
 */
class McpLinksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Limit: a model event does not fire for a write that bypasses the model
        // (a query-builder update, saveQuietly). The one such path in the code is
        // UsersSource (data migration, users just created). There the tokens
        // already issued stay valid until they expire, at most the exchange token
        // lifetime (mcp-link.access_token_ttl_minutes); the next exchange is refused.
        User::updated(fn (User $user) => $this->app->make(DropMcpLinksOnCredentialChange::class)->handle($user));
    }
}
