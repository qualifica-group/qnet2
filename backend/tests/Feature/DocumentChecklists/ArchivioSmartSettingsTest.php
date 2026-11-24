<?php

use App\Models\ArchivioSmartSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

/*
| ArchivioSmart connection endpoints (spec 0403): AC-007 (connection part),
| AC-017, AC-018, AC-019. Http is always faked: no real call.
*/

uses(RefreshDatabase::class);

const ARCHIVIO_SMART_TOKEN = 'tok_super_secret_value_123';
const ARCHIVIO_SMART_URL = 'https://archivio.example.test/api/v1';

function archivioSmartManager(): void
{
    Sanctum::actingAs(documentChecklistUserWith(['manageConnection']));
}

it('AC-007: the three endpoints answer 403 without manageConnection, even with every other permission', function (string $method, string $uri) {
    Http::fake();
    Sanctum::actingAs(documentChecklistUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity']));

    $this->json($method, $uri, ['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN])->assertForbidden();

    expect(ArchivioSmartSetting::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'show' => ['GET', '/api/archivio-smart/settings'],
    'update' => ['PUT', '/api/archivio-smart/settings'],
    'test' => ['POST', '/api/archivio-smart/settings/test'],
]);

it('AC-007: unauthenticated callers get 401', function () {
    $this->getJson('/api/archivio-smart/settings')->assertUnauthorized();
});

it('GET answers an empty connection when nothing is saved', function () {
    archivioSmartManager();

    $this->getJson('/api/archivio-smart/settings')
        ->assertOk()
        ->assertJsonPath('data', [
            'base_url' => null,
            'has_token' => false,
            // REQUIREMENT CHANGED (spec 0428): the connection also holds the webhook secret and exposes the webhook URL.
            'has_webhook_secret' => false,
            'webhook_url' => url('/api/webhooks/archivio-smart'),
            'updated_at' => null,
        ]);
});

it('AC-017: GET and PUT never return the token nor any part of it, and it is encrypted at rest', function () {
    archivioSmartManager();

    $put = $this->putJson('/api/archivio-smart/settings', ['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN])
        ->assertOk()
        ->assertJsonPath('data.base_url', ARCHIVIO_SMART_URL)
        ->assertJsonPath('data.has_token', true);
    $get = $this->getJson('/api/archivio-smart/settings')->assertOk()->assertJsonPath('data.has_token', true);

    foreach ([$put->getContent(), $get->getContent()] as $body) {
        expect($body)->not->toContain(ARCHIVIO_SMART_TOKEN)
            ->and($body)->not->toContain('tok_super')
            ->and($body)->not->toContain('secret_value')
            ->and($body)->not->toContain('"token"');
    }

    $raw = DB::table('archivio_smart_settings')->value('token');
    expect($raw)->not->toBe(ARCHIVIO_SMART_TOKEN)
        ->and($raw)->not->toContain('secret_value')
        ->and(ArchivioSmartSetting::current()->token)->toBe(ARCHIVIO_SMART_TOKEN);
});

it('AC-017: the activity log records the connection but never the token', function () {
    archivioSmartManager();

    $this->putJson('/api/archivio-smart/settings', ['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN])->assertOk();
    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'https://other.example.test', 'token' => 'another_secret_token_999'])->assertOk();

    $activities = Activity::query()->where('subject_type', 'archivio_smart_setting')->get();
    expect($activities)->not->toBeEmpty();

    $dump = $activities->toJson();
    expect($dump)->toContain('archivio.example.test')
        ->and($dump)->not->toContain(ARCHIVIO_SMART_TOKEN)
        ->and($dump)->not->toContain('another_secret_token_999')
        ->and($dump)->not->toContain('secret_value')
        ->and($dump)->not->toContain('"token"');
});

it('AC-018: a PUT without token (absent or empty) keeps the saved one, a new token replaces it', function () {
    archivioSmartManager();
    $this->putJson('/api/archivio-smart/settings', ['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN])->assertOk();

    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'https://changed.example.test'])
        ->assertOk()
        ->assertJsonPath('data.has_token', true);
    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'https://changed.example.test', 'token' => ''])->assertOk();

    expect(ArchivioSmartSetting::query()->count())->toBe(1)
        ->and(ArchivioSmartSetting::current()->base_url)->toBe('https://changed.example.test')
        ->and(ArchivioSmartSetting::current()->token)->toBe(ARCHIVIO_SMART_TOKEN);

    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'https://changed.example.test', 'token' => 'brand_new_token_456'])->assertOk();

    expect(ArchivioSmartSetting::query()->count())->toBe(1)
        ->and(ArchivioSmartSetting::current()->token)->toBe('brand_new_token_456');
});

it('AC-018: validates base_url and token', function (array $payload, string $field) {
    archivioSmartManager();

    $this->putJson('/api/archivio-smart/settings', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(ArchivioSmartSetting::query()->count())->toBe(0);
})->with([
    'missing url' => [['token' => ARCHIVIO_SMART_TOKEN], 'base_url'],
    'not a url' => [['base_url' => 'not a url', 'token' => ARCHIVIO_SMART_TOKEN], 'base_url'],
    'ftp scheme' => [['base_url' => 'ftp://archivio.example.test', 'token' => ARCHIVIO_SMART_TOKEN], 'base_url'],
    'url too long' => [['base_url' => 'https://example.test/'.str_repeat('a', 260), 'token' => ARCHIVIO_SMART_TOKEN], 'base_url'],
    'token too short' => [['base_url' => ARCHIVIO_SMART_URL, 'token' => 'short'], 'token'],
    'token too long' => [['base_url' => ARCHIVIO_SMART_URL, 'token' => str_repeat('t', 501)], 'token'],
]);

it('AC-018: http is accepted in testing but rejected in production', function () {
    archivioSmartManager();
    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'http://localhost:9000/api', 'token' => ARCHIVIO_SMART_TOKEN])->assertOk();

    app()->detectEnvironment(fn () => 'production');

    $this->putJson('/api/archivio-smart/settings', ['base_url' => 'http://archivio.example.test', 'token' => ARCHIVIO_SMART_TOKEN])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('base_url');
    $this->putJson('/api/archivio-smart/settings', ['base_url' => ARCHIVIO_SMART_URL])->assertOk();
});

it('AC-019: test answers ok on 200 and calls GET /me with the bearer token', function () {
    Http::fake(['*' => Http::response(['id' => 1], 200)]);
    archivioSmartManager();
    ArchivioSmartSetting::factory()->create(['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN]);

    $response = $this->postJson('/api/archivio-smart/settings/test')
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.http_status', 200)
        ->assertJsonPath('data.message', __('archivio_smart.connection_ok'));

    expect($response->getContent())->not->toContain(ARCHIVIO_SMART_TOKEN);
    Http::assertSent(fn ($request) => $request->url() === ARCHIVIO_SMART_URL.'/me'
        && $request->hasHeader('Authorization', 'Bearer '.ARCHIVIO_SMART_TOKEN));
});

it('AC-019: test reports a non-200 status without leaking the token or the remote body', function () {
    Http::fake(['*' => Http::response(['detail' => 'secret remote body '.ARCHIVIO_SMART_TOKEN], 401)]);
    archivioSmartManager();
    ArchivioSmartSetting::factory()->create(['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN]);

    $response = $this->postJson('/api/archivio-smart/settings/test')
        ->assertOk()
        ->assertJsonPath('data.ok', false)
        ->assertJsonPath('data.http_status', 401)
        ->assertJsonPath('data.message', __('archivio_smart.connection_failed', ['status' => 401]));

    expect($response->getContent())->not->toContain(ARCHIVIO_SMART_TOKEN)->and($response->getContent())->not->toContain('remote body');
});

it('AC-019: test reports a network error when ArchivioSmart is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: could not resolve host'));
    archivioSmartManager();
    ArchivioSmartSetting::factory()->create(['base_url' => ARCHIVIO_SMART_URL, 'token' => ARCHIVIO_SMART_TOKEN]);

    $response = $this->postJson('/api/archivio-smart/settings/test')
        ->assertOk()
        ->assertJsonPath('data.ok', false)
        ->assertJsonPath('data.http_status', null)
        ->assertJsonPath('data.message', __('archivio_smart.network_error'));

    expect($response->getContent())->not->toContain('cURL');
});

it('AC-019: test answers 422 when the connection is not configured', function () {
    Http::fake();
    archivioSmartManager();

    $this->postJson('/api/archivio-smart/settings/test')
        ->assertUnprocessable()
        ->assertJsonPath('message', __('archivio_smart.connection_not_configured'));

    ArchivioSmartSetting::factory()->create(['base_url' => ARCHIVIO_SMART_URL, 'token' => null]);
    $this->postJson('/api/archivio-smart/settings/test')->assertUnprocessable();

    Http::assertNothingSent();
});
