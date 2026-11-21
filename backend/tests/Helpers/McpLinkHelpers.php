<?php

declare(strict_types=1);

use App\Models\McpLink;
use App\Models\User;

if (! function_exists('openLinkFor')) {
    /**
     * Opens a link through the real route.
     *
     * @return array{0: string, 1: string, 2: User, 3: McpLink, 4: int} key, credential, user, link, client id
     */
    function openLinkFor(?User $user = null): array
    {
        [$client, $key] = createApiClientWithKey();
        $user ??= User::factory()->create();

        $response = callWithToken('POST', '/api/mcp/links', $key, [
            'email' => $user->email, 'password' => 'password', 'mcp_client' => 'test-mcp-client',
        ])->assertCreated();

        return [$key, $response->json('data.credential'), $user, McpLink::query()->findOrFail($response->json('data.link_id')), $client->id];
    }
}
