<?php

namespace App\Providers;

use App\Listeners\McpLinks\DropMcpLinksOnCredentialChange;
use App\Models\McpLink;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

/**
 * Wiring of the MCP link module: the credential-change listener (policies in the listener) and the
 * activity log tagging (spec 0434). The routes are registered in bootstrap/app.php, like the API clients ones.
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
        User::updating(fn (User $user) => $this->app->make(DropMcpLinksOnCredentialChange::class)->beforeUpdate($user));
        User::updated(fn (User $user) => $this->app->make(DropMcpLinksOnCredentialChange::class)->afterUpdate($user));

        // The causer stays the user of the link; the link the change went through is
        // recorded next to it, so the activity log shows the change as made with MCP AI.
        Activity::creating(function (Activity $activity): void {
            $linkId = request()->attributes->get(McpLink::REQUEST_ATTRIBUTE);

            if ($linkId !== null) {
                $activity->properties = $activity->properties->put(McpLink::ACTIVITY_PROPERTY, $linkId);
            }
        });
    }
}
