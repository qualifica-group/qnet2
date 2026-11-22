<?php

declare(strict_types=1);

use App\Models\McpLink;
use App\Models\User;
use Illuminate\Cache\ArrayStore;

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

if (! class_exists('InterleavingArrayStore')) {
    /**
     * Array store whose increment is a read, a pause and a write, like a non-atomic backend
     * increment: `$between` runs in the pause, once, where a concurrent request would.
     */
    final class InterleavingArrayStore extends ArrayStore
    {
        public static ?Closure $between = null;

        public function increment($key, $value = 1)
        {
            $current = (int) $this->get($key);

            if (self::$between !== null && str_contains($key, 'mcp-link-open')) {
                $hook = self::$between;
                self::$between = null;
                $hook();
            }

            $this->put($key, $current + $value, 900);

            return $current + $value;
        }
    }
}
