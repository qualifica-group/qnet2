<?php

use App\Models\AiAssistantSetting;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 0433, D-7: a new settings row is born with monthly caps (a row without them would mean no limit at all).
it('creates the settings row with the monthly caps of the configuration', function () {
    $settings = app(AiAssistantSettingsService::class)->effective();

    expect($settings->spendCapTotalUsd)->toBe('20.00')->and($settings->spendCapPersonUsd)->toBe('5.00');
});

it('reads the initial caps from the configuration', function () {
    config()->set('ai-assistant.settings.default_spend_cap_total_usd', '100');
    config()->set('ai-assistant.settings.default_spend_cap_person_usd', '12.5');

    $settings = app(AiAssistantSettingsService::class)->effective();

    expect($settings->spendCapTotalUsd)->toBe('100.00')->and($settings->spendCapPersonUsd)->toBe('12.50');
});

it('leaves no cap when the configuration gives none, and never overwrites a saved row', function () {
    config()->set('ai-assistant.settings.default_spend_cap_total_usd', null);
    config()->set('ai-assistant.settings.default_spend_cap_person_usd', null);
    expect(app(AiAssistantSettingsService::class)->effective()->spendCapTotalUsd)->toBeNull();

    config()->set('ai-assistant.settings.default_spend_cap_total_usd', '50');
    AiAssistantSetting::query()->update(['spend_cap_total_usd' => '7']);

    expect(app(AiAssistantSettingsService::class)->effective()->spendCapTotalUsd)->toBe('7.00');
});
