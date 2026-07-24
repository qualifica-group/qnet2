<?php

namespace App\Migrations\Support;

use App\Exceptions\ExternalApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around Illuminate\Support\Facades\Http for the external
 * migration source (spec 0013): base URL + Bearer token + timeout from
 * config/migrations.php (env-backed), with a bounded retry. Normalizes every
 * external failure into ExternalApiException so no MigrationSource ever has
 * to know the transport details:
 *
 * - non-2xx response       -> 502
 * - connection failure     -> 502
 * - timeout                -> 504
 *
 * Every message is a safe, static string: never the URL, never the token,
 * never the raw upstream body (backend.md §8 / security.md).
 */
class ExternalApiClient
{
    private readonly ?string $baseUrl;

    private readonly ?string $token;

    private readonly int $timeout;

    private readonly int $retryTimes;

    private readonly int $retrySleepMs;

    /**
     * Reads config/migrations.php directly (no constructor args) so the
     * container can autowire this class with zero bindings, exactly like the
     * rest of the app's Services — the config is re-read on every resolution,
     * so tests can override it with config([...]) before the request runs.
     */
    public function __construct()
    {
        $this->baseUrl = config('migrations.base_url');
        $this->token = config('migrations.token');
        $this->timeout = (int) config('migrations.timeout', 15);
        $this->retryTimes = (int) config('migrations.retry_times', 2);
        $this->retrySleepMs = (int) config('migrations.retry_sleep_ms', 200);
    }

    /**
     * GET the given path (relative to the configured base URL), decoded JSON
     * body on a 2xx response.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws ExternalApiException on any non-2xx response, connection
     *                              failure, or timeout.
     */
    public function get(string $path, array $query = []): array
    {
        try {
            // Never follow redirects: an auth/session redirect (e.g. to a
            // login page) must surface as an error, not be followed to a 200
            // HTML page that silently decodes to an empty payload — otherwise
            // every import "succeeds" with zero rows.
            $response = $this->client()->withoutRedirecting()->get($path, $query);
        } catch (ConnectionException $exception) {
            throw $this->isTimeout($exception)
                ? new ExternalApiException('The external system timed out.', 504, $exception)
                : new ExternalApiException('Could not reach the external system.', 502, $exception);
        }

        if ($response->redirect()) {
            throw new ExternalApiException('The external system rejected the request (authentication required).', 502);
        }

        if ($response->failed()) {
            throw new ExternalApiException('The external system returned an error.', 502);
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new ExternalApiException('The external system returned an invalid (non-JSON) response.', 502);
        }

        return $decoded;
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl((string) $this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs);

        if ($this->token !== null && $this->token !== '') {
            $client = $client->withToken($this->token);
        }

        return $client;
    }

    /**
     * cURL surfaces a timeout as a ConnectionException whose message mentions
     * the timeout (e.g. "cURL error 28: Operation timed out"); every other
     * connection failure (DNS, refused, ...) is a plain 502.
     */
    private function isTimeout(ConnectionException $exception): bool
    {
        return str_contains(mb_strtolower($exception->getMessage()), 'timed out')
            || str_contains(mb_strtolower($exception->getMessage()), 'timeout');
    }
}
