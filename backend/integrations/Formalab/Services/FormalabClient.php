<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Exceptions\FormalabException;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Support\Trigger;

/**
 * The only door to the Formalab API: login with a cached token, one retry
 * after a 401, TLS verified, and a formalab_call_logs row for EVERY request
 * (login included). Credentials and token never reach the logs.
 */
final class FormalabClient
{
    private const string TOKEN_CACHE_KEY = 'formalab.access_token';

    private const string LOGIN_PATH = '/api/login';

    public function __construct(private readonly CallLogger $logger) {}

    /**
     * Authenticated GET returning the decoded JSON body.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws FormalabException not configured, unreachable, or an error status
     */
    public function get(string $path, array $query, Trigger $trigger, ?int $timeoutSeconds = null): array
    {
        $this->assertConfigured();
        $timeout = $timeoutSeconds ?? (int) config('formalab.timeout_seconds');

        // Step 1: first attempt with the cached (or freshly obtained) token
        $response = $this->attempt($path, $query, $trigger, $timeout);

        // Step 2: a rejected token is dropped and the call retried exactly once
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->attempt($path, $query, $trigger, $timeout);
        }

        // Step 3: anything but a 2xx is a failure for the caller
        if (! $response->successful()) {
            throw new FormalabException(__('formalab::messages.http_error', ['status' => $response->status()]), $response->status());
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * Whether base URL and credentials are all set.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->email() !== '' && $this->password() !== '';
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new FormalabException(__('formalab::messages.not_configured'));
        }
    }

    private function attempt(string $path, array $query, Trigger $trigger, int $timeout): Response
    {
        $token = $this->token($trigger, $timeout);

        return $this->send('GET', $path, $query, $trigger, $timeout, $token, $query);
    }

    private function token(Trigger $trigger, int $timeout): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->send('POST', self::LOGIN_PATH, [
            'email' => $this->email(),
            'password' => $this->password(),
        ], $trigger, $timeout, null, null);

        $token = $response->json('accessToken');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new FormalabException(__('formalab::messages.login_failed'), $response->status());
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addMinutes((int) config('formalab.token_ttl_minutes')));

        return $token;
    }

    /**
     * One HTTP request, always logged. Transport failures are logged and
     * rethrown as FormalabException.
     *
     * @param  array<string, mixed>  $payload  query (GET) or body (POST)
     * @param  array<string, mixed>|null  $loggedSummary  what the log may keep of the request
     */
    private function send(string $method, string $path, array $payload, Trigger $trigger, int $timeout, ?string $token, ?array $loggedSummary): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $this->pending($timeout, $token)->send($method, $path, $method === 'GET' ? ['query' => $payload] : ['json' => $payload]);
        } catch (ConnectionException $exception) {
            $this->logger->record(FormalabCallLog::DIRECTION_OUTBOUND, $method, $path, null, $this->elapsedMs($startedAt), $trigger, $loggedSummary, $exception->getMessage());

            throw new FormalabException(__('formalab::messages.unreachable'));
        }

        $this->logger->record(
            FormalabCallLog::DIRECTION_OUTBOUND,
            $method,
            $path,
            $response->status(),
            $this->elapsedMs($startedAt),
            $trigger,
            $loggedSummary,
            $response->successful() ? null : $this->failureMessage($response),
            $response->successful() ? $this->summarize($response) : null,
        );

        return $response;
    }

    private function pending(int $timeout, ?string $token): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())->timeout($timeout)->acceptJson();

        return $token === null ? $request : $request->withToken($token);
    }

    private function failureMessage(Response $response): string
    {
        return 'HTTP '.$response->status().' '.$response->reason();
    }

    private function summarize(Response $response): ?string
    {
        $classes = $response->json('classi');

        if (is_array($classes)) {
            return sprintf('%d classes, page %s/%s', count($classes), $response->json('current_page') ?? 1, $response->json('last_page') ?? 1);
        }

        return is_array($response->json('classe')) ? '1 class' : null;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('formalab.url'), '/');
    }

    private function email(): string
    {
        return (string) config('formalab.user_email');
    }

    private function password(): string
    {
        return (string) config('formalab.user_password');
    }
}
