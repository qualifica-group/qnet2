<?php

namespace App\Providers;

use App\Http\Middleware\EnsureApiClientKey;
use App\Listeners\McpLinks\DropMcpLinksOnCredentialChange;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring of the MCP link module: routes and the credential-change listener.
 */
class McpLinksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::updated(fn (User $user) => $this->app->make(DropMcpLinksOnCredentialChange::class)->handle($user));

        if (! $this->app->routesAreCached()) {
            Route::middleware(['api', 'auth:sanctum', EnsureApiClientKey::class])
                ->prefix('api')
                ->group(base_path('routes/api/mcp-links.php'));
        }
    }
}
