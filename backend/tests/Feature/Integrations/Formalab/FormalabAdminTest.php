<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\CourseFieldWriter;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-010
it('serves the status page data to a super-admin without any secret', function () {
    formalabEnable(['url' => 'https://formalab.test/some/path?x=1']);
    FormalabClass::query()->create(['edition_id' => 1, 'code' => 'A', 'title' => 'B', 'label' => 'A - B', 'payload_hash' => str_repeat('c', 64)]);
    FormalabSyncRun::query()->create(['trigger' => 'schedule', 'outcome' => 'success', 'started_at' => now(), 'finished_at' => now(), 'classes_synced' => 1]);
    FormalabCallLog::query()->create(['direction' => 'outbound', 'method' => 'GET', 'endpoint' => '/api/classi', 'outcome' => 'success', 'status_code' => 200, 'trigger' => 'schedule']);
    FormalabCallLog::query()->create(['direction' => 'inbound', 'method' => 'POST', 'endpoint' => '/x', 'outcome' => 'failure', 'status_code' => 401, 'trigger' => 'webhook']);
    Sanctum::actingAs(formalabSuperAdmin());

    $response = $this->getJson('/api/integrations/formalab/status')->assertOk();

    $response->assertJsonPath('data.base_url', 'https://formalab.test')->assertJsonPath('data.credentials_configured', true)
        ->assertJsonPath('data.inbound_enabled', true)->assertJsonPath('data.sync_interval_minutes', 10)
        ->assertJsonPath('data.classes_count', 1)->assertJsonPath('data.linked_work_orders_count', 0)
        ->assertJsonPath('data.last_sync.outcome', 'success')->assertJsonPath('data.last_sync.warning_message', null)
        ->assertJsonPath('data.missing_course_fields', CourseFieldWriter::CODES)
        ->assertJsonPath('data.calls_last_24h', ['total' => 2, 'failures' => 1, 'inbound' => 1, 'outbound' => 1]);
    expect($response->getContent())->not->toContain('p4ss-never-logged')->not->toContain('inbound-secret-token');
});

it('queues the sync and tests the connection for a super-admin', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1)]);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/sync')->assertStatus(202)->assertJsonPath('data', null);
    expect(FormalabSyncRun::query()->where('trigger', 'manual')->count())->toBe(1)
        ->and(FormalabClass::query()->count())->toBe(1);

    $this->postJson('/api/integrations/formalab/test-connection')->assertOk()
        ->assertJsonPath('data.ok', true)->assertJsonPath('data.message', null);
});

it('reports a failed connection test as data, not as an error', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi*' => Http::response('x', 500)]);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/test-connection')->assertOk()
        ->assertJsonPath('data.ok', false)->assertJsonPath('data.latency_ms', null);
});

it('refuses the admin endpoints to everyone else', function () {
    formalabEnable();
    Sanctum::actingAs(User::factory()->create());

    foreach (['get' => 'status', 'post' => 'sync'] as $method => $path) {
        $this->{$method.'Json'}("/api/integrations/formalab/{$path}")->assertForbidden();
    }
    $this->postJson('/api/integrations/formalab/test-connection')->assertForbidden();
    $this->postJson('/api/integrations/formalab/link-work-orders')->assertForbidden();
    $this->getJson('/api/tables/formalab-call-logs/columns')->assertForbidden();
    expect(FormalabWorkOrderLink::query()->count())->toBe(0);
});

it('serves the call logs table to a super-admin, newest first', function () {
    formalabEnable();
    formalabCallLog(['endpoint' => '/old'], now()->subDay());
    formalabCallLog(['direction' => 'inbound', 'method' => 'POST', 'endpoint' => '/new', 'outcome' => 'failure', 'status_code' => 401, 'trigger' => 'webhook']);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->getJson('/api/tables/formalab-call-logs/columns')->assertOk();
    $rows = $this->postJson('/api/tables/formalab-call-logs/rows', ['startRow' => 0, 'endRow' => 10])->assertOk();

    expect(array_column($rows->json('items'), 'endpoint'))->toBe(['/new', '/old'])->and($rows->json('items.0.editable'))->toBeFalse();
});
