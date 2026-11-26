<?php

use App\Models\AiCostEntry;
use App\Models\AiCostReservation;
use App\Models\AiSpendCounter;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\AgentModelException;
use App\Services\AiAssistant\Agent\AgentRun;
use App\Services\AiAssistant\Agent\MeteredModelCall;
use App\Services\AiAssistant\Agent\OpenRouterAgentClient;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use App\Services\AiAssistant\Costs\AiSpendLedger;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use App\Services\AiAssistant\Settings\OpenRouterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    aiapi_configure(openLinks: false);
    Cache::flush();
    $catalog = Mockery::mock(OpenRouterCatalog::class);
    $catalog->shouldReceive('priceCeiling')->andReturn(new AiPriceCeiling('0.000001', '0.000002', '0'));
    $catalog->shouldReceive('supportsReasoning')->andReturn(false);
    $this->metered = new MeteredModelCall(app(OpenRouterAgentClient::class), $catalog, app(AiSpendLedger::class));
    $this->run = new AgentRun(AiTurn::factory()->create());
    $this->settings = new AiEffectiveSettings(true, 'vendor/model-y', '', ['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput'], null, null);
});

// Spec "Costi e tetti": an unexpected failure after the call started may have been billed, so the cap holds on the reservation.
it('settles at the reservation when an unexpected error follows the first fragment', function () {
    Http::fake(['openrouter.test/*' => Http::response(aiapi_text_body(), 200, ['Content-Type' => 'text/event-stream'])]);

    expect(fn () => $this->metered->call($this->run, $this->settings, [['role' => 'user', 'content' => 'hi']], [], fn () => throw new LogicException('boom')))
        ->toThrow(AgentModelException::class);

    $entry = AiCostEntry::query()->sole();
    expect($entry->cost_usd)->toBe($entry->reserved_usd)
        ->and($entry->signal)->toBe(AiSpendLedger::SIGNAL_COST_MISSING)
        ->and(AiCostReservation::query()->count())->toBe(0)
        ->and(AiSpendCounter::query()->where('user_id', AiSpendCounter::TOTAL_USER_ID)->value('spent_usd'))->toBe($entry->cost_usd);
});

it('frees the reservation when the model fails before any of the response arrived', function () {
    Http::fake(['openrouter.test/*' => Http::response('', 500)]);

    expect(fn () => $this->metered->call($this->run, $this->settings, [['role' => 'user', 'content' => 'hi']], [], fn () => null))
        ->toThrow(AgentModelException::class);

    expect(AiCostEntry::query()->count())->toBe(0)->and(AiCostReservation::query()->count())->toBe(0);
});
