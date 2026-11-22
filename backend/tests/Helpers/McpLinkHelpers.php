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
     * Array store that pauses before each write of an opening counter, after the counter was
     * read: `$between` runs in the pause, once, where a concurrent request would. `add` stays
     * atomic, as it is on the real stores.
     */
    final class InterleavingArrayStore extends ArrayStore
    {
        public static ?Closure $between = null;

        public function add($key, $value, $seconds)
        {
            return $this->get($key) === null && parent::put($key, $value, $seconds);
        }

        public function put($key, $value, $seconds)
        {
            $this->pause($key);

            return parent::put($key, $value, $seconds);
        }

        public function increment($key, $value = 1)
        {
            $this->pause($key);

            return parent::increment($key, $value);
        }

        private function pause(string $key): void
        {
            if (self::$between !== null && str_contains($key, 'mcp-link-open')) {
                $hook = self::$between;
                self::$between = null;
                $hook();
            }
        }
    }
}

if (! class_exists('RefusingArrayStore')) {
    /**
     * A store that is reachable but refuses the named writes, as a degraded backend does.
     */
    final class RefusingArrayStore extends ArrayStore
    {
        /**
         * @param  list<string>  $refused  among add, put, increment
         */
        public function __construct(private readonly array $refused)
        {
            parent::__construct();
        }

        public function add($key, $value, $seconds)
        {
            return ! in_array('add', $this->refused, true) && $this->get($key) === null && parent::put($key, $value, $seconds);
        }

        public function put($key, $value, $seconds)
        {
            return ! in_array('put', $this->refused, true) && parent::put($key, $value, $seconds);
        }

        public function increment($key, $value = 1)
        {
            return in_array('increment', $this->refused, true) ? false : parent::increment($key, $value);
        }
    }
}
