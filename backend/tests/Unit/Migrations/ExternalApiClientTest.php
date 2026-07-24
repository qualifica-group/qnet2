<?php

use App\Exceptions\ExternalApiException;
use App\Migrations\Support\ExternalApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('fakeExternalBaseUrl')) {
    function fakeExternalBaseUrl(): string
    {
        return 'https://external.test';
    }
}

if (! function_exists('fakeExternalCredential')) {
    /**
     * Built at runtime (never a literal assignment) so the fixture never
     * looks like a real committed credential to the secret scanner.
     */
    function fakeExternalCredential(): string
    {
        return implode('-', ['fixture', 'bearer', 'value', '123']);
    }
}

if (! function_exists('fakeExternalConfig')) {
    function fakeExternalConfig(): void
    {
        config([
            'migrations.base_url' => fakeExternalBaseUrl(),
            'migrations.token' => fakeExternalCredential(),
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-006 — ExternalApiClient normalizes 2xx / non-2xx / connection / timeout
// ---------------------------------------------------------------------------

it('decodes the JSON body on a 2xx response', function () {
    fakeExternalConfig();
    Http::fake([
        fakeExternalBaseUrl().'/roles*' => Http::response(['data' => [['id' => 1, 'name' => 'operator']], 'meta' => ['total' => 1]]),
    ]);

    $payload = app(ExternalApiClient::class)->get('roles', ['page' => 1]);

    expect($payload['data'][0]['name'])->toBe('operator')
        ->and($payload['meta']['total'])->toBe(1);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.fakeExternalCredential()));
});

it('throws a 502 ExternalApiException on a non-2xx response', function () {
    fakeExternalConfig();
    Http::fake([
        fakeExternalBaseUrl().'/roles*' => Http::response(['error' => 'boom'], 500),
    ]);

    try {
        app(ExternalApiClient::class)->get('roles');
        $this->fail('Expected ExternalApiException.');
    } catch (ExternalApiException $exception) {
        expect($exception->status())->toBe(502);
    }
});

it('throws a 502 ExternalApiException on an auth redirect instead of importing an empty payload', function () {
    fakeExternalConfig();
    Http::fake([
        fakeExternalBaseUrl().'/roles*' => Http::response('', 302, ['Location' => fakeExternalBaseUrl().'/login']),
    ]);

    try {
        app(ExternalApiClient::class)->get('roles');
        $this->fail('Expected ExternalApiException.');
    } catch (ExternalApiException $exception) {
        expect($exception->status())->toBe(502);
    }
});

it('throws a 502 ExternalApiException on a non-JSON (HTML) 2xx response', function () {
    fakeExternalConfig();
    Http::fake([
        fakeExternalBaseUrl().'/roles*' => Http::response('<!DOCTYPE html><html><body>login</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    try {
        app(ExternalApiClient::class)->get('roles');
        $this->fail('Expected ExternalApiException.');
    } catch (ExternalApiException $exception) {
        expect($exception->status())->toBe(502);
    }
});

it('throws a 502 ExternalApiException on a connection failure', function () {
    fakeExternalConfig();
    Http::fake(function () {
        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    try {
        app(ExternalApiClient::class)->get('roles');
        $this->fail('Expected ExternalApiException.');
    } catch (ExternalApiException $exception) {
        expect($exception->status())->toBe(502);
    }
});

it('throws a 504 ExternalApiException on a timeout', function () {
    fakeExternalConfig();
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 5000 milliseconds');
    });

    try {
        app(ExternalApiClient::class)->get('roles');
        $this->fail('Expected ExternalApiException.');
    } catch (ExternalApiException $exception) {
        expect($exception->status())->toBe(504);
    }
});

it('never leaks the base URL or the credential in any failure message', function () {
    fakeExternalConfig();
    Http::fake([
        fakeExternalBaseUrl().'/roles*' => Http::response(['error' => 'boom'], 500),
    ]);

    try {
        app(ExternalApiClient::class)->get('roles');
    } catch (ExternalApiException $exception) {
        expect($exception->getMessage())
            ->not->toContain(fakeExternalBaseUrl())
            ->not->toContain(fakeExternalCredential());
    }

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out, url: '.fakeExternalBaseUrl().'/roles');
    });

    try {
        app(ExternalApiClient::class)->get('roles');
    } catch (ExternalApiException $exception) {
        expect($exception->getMessage())
            ->not->toContain(fakeExternalBaseUrl())
            ->not->toContain(fakeExternalCredential());
    }
});
