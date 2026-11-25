<?php

use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\AiDefaultInstructions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns the initial values of the spec on first use', function () {
    $settings = app(AiAssistantSettingsService::class)->effective();

    expect($settings->enabled)->toBeTrue()
        ->and($settings->modelId)->toBe('deepseek/deepseek-v4-flash-0731')
        ->and($settings->routing)->toBe(['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput'])
        ->and($settings->instructions)->toBe(AiDefaultInstructions::TEXT)
        ->and($settings->spendCapPersonUsd)->toBeNull();
});

it('returns what the super-admin saved, and the switch off', function () {
    Http::fake();
    $service = app(AiAssistantSettingsService::class);

    $service->update([
        'enabled' => false,
        'instructions' => null,
        'routing' => ['sort' => 'price', 'zdr' => false],
        'spend_cap_total_usd' => '120',
    ], ais_super_admin());

    $settings = $service->effective();
    expect($settings->enabled)->toBeFalse()
        ->and($settings->instructions)->toBe('')
        ->and($settings->routing)->toBe(['zdr' => false, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'price'])
        ->and($settings->spendCapTotalUsd)->toBe('120.00');
});

it('keeps the initial instructions inside the configured limit', function () {
    expect(mb_strlen(AiDefaultInstructions::TEXT))->toBeLessThanOrEqual((int) config('ai-assistant.settings.instructions_max_chars'));
});
