<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

uses(RefreshDatabase::class);

const FORMALAB_WEBHOOK_URL = '/api/integrations/formalab/webhooks/classes';

function formalabWebhook(array $payload, ?string $token = 'inbound-secret-token')
{
    return test()->postJson(FORMALAB_WEBHOOK_URL, $payload, $token === null ? [] : ['X-Formalab-Token' => $token]);
}

// AC-009
it('refreshes the given editions and updates the linked work orders', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(3, ['stato_corso' => ['name' => 'Concluso']])]);
    $workOrder = formalabGolWorkOrder();
    FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => 3]);

    formalabWebhook(['edition_ids' => [3, 3, 99]])->assertOk()
        ->assertJsonPath('data', ['refreshed' => 1, 'work_orders_updated' => 1]);

    expect(FormalabClass::query()->where('edition_id', 3)->exists())->toBeTrue()
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('Concluso')
        ->and(FormalabSyncRun::query()->where('trigger', 'webhook')->value('outcome'))->toBe('success');

    $log = FormalabCallLog::query()->where('direction', 'inbound')->first();
    expect($log->outcome)->toBe('success')->and($log->status_code)->toBe(200)
        ->and($log->endpoint)->toBe('/api/integrations/formalab/webhooks/classes')
        ->and($log->response_summary)->toBe('1 classes refreshed, 1 work orders updated');
});

it('rejects a wrong or missing token with 401 and logs it as an inbound failure', function () {
    formalabEnable();
    Http::preventStrayRequests();

    formalabWebhook(['edition_ids' => [1]], 'wrong-token')->assertUnauthorized();
    formalabWebhook(['edition_ids' => [1]], null)->assertUnauthorized();

    $logs = FormalabCallLog::query()->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('direction')->unique()->all())->toBe(['inbound'])
        ->and($logs->pluck('outcome')->unique()->all())->toBe(['failure'])
        ->and($logs->pluck('status_code')->unique()->all())->toBe([401])
        ->and(json_encode($logs->toArray()))->not->toContain('wrong-token')->not->toContain('inbound-secret-token')
        ->and(FormalabClass::query()->count())->toBe(0);
});

it('does not exist when no inbound token is configured', function () {
    formalabEnable(['inbound_token' => '']);

    formalabWebhook(['edition_ids' => [1]], 'anything')->assertNotFound();
});

it('rejects an invalid payload with 422 and logs the call', function () {
    formalabEnable();

    formalabWebhook(['edition_ids' => []])->assertUnprocessable();
    formalabWebhook(['edition_ids' => range(1, 51)])->assertUnprocessable();

    expect(FormalabCallLog::query()->where('status_code', 422)->count())->toBe(2);
});

it('answers 502 when Formalab cannot be read and records the failed run', function () {
    formalabEnable();
    Http::preventStrayRequests();
    Http::fake(['*/api/login' => Http::response(['accessToken' => 't']), '*/api/classi/*' => Http::response('x', 500)]);

    formalabWebhook(['edition_ids' => [1]])->assertStatus(502);

    expect(FormalabSyncRun::query()->where('trigger', 'webhook')->value('outcome'))->toBe('failure');
});
