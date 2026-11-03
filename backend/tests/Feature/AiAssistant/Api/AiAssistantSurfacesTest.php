<?php

use App\Models\AiMessage;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
| Spec 0347, D-16: AI_ASSISTANT_FLOATING_ENABLED / AI_ASSISTANT_PAGE_ENABLED
| switch the floating chat and the page on separately; both off by default.
*/

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

function aisurf_menu_has_assistant(User $user): bool
{
    return collect(app(NavigationService::class)->for($user))->contains('key', 'ai-assistant');
}

it('AC-031: ships both surfaces switched off', function () {
    // The local .env may switch them on: read the config file with the variables unset.
    $names = ['AI_ASSISTANT_FLOATING_ENABLED', 'AI_ASSISTANT_PAGE_ENABLED'];
    $saved = array_map(fn (string $name): array => [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)], $names);
    foreach ($names as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }

    try {
        $config = require config_path('ai-assistant.php');
    } finally {
        foreach ($names as $index => $name) {
            [$env, $server, $process] = $saved[$index];
            if ($env !== null) {
                $_ENV[$name] = $env;
            }
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($process !== false) {
                putenv("{$name}={$process}");
            }
        }
    }

    expect($config['floating_enabled'])->toBeFalse()
        ->and($config['page_enabled'])->toBeFalse();
});

it('AC-031: exposes the two switches on GET /config', function (bool $floating, bool $page) {
    config()->set('ai-assistant.floating_enabled', $floating);
    config()->set('ai-assistant.page_enabled', $page);
    Sanctum::actingAs(aiapi_user());

    $this->getJson('/api/ai-assistant/config')
        ->assertOk()
        ->assertJsonPath('data.floating_enabled', $floating)
        ->assertJsonPath('data.page_enabled', $page);
})->with([
    'both off' => [false, false],
    'floating only' => [true, false],
    'page only' => [false, true],
]);

it('AC-032: answers 404 on every endpoint but /config while both surfaces are off', function () {
    config()->set('ai-assistant.floating_enabled', false);
    config()->set('ai-assistant.page_enabled', false);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    $message = AiMessage::factory()->for($conversation, 'conversation')->create();
    $attachment = $message->attach(aiapi_pdf());
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-assistant/config')->assertOk();
    $this->getJson('/api/ai-assistant/conversations')->assertNotFound();
    $this->postJson('/api/ai-assistant/conversations')->assertNotFound();
    $this->getJson("/api/ai-assistant/conversations/{$conversation->id}")->assertNotFound();
    $this->deleteJson("/api/ai-assistant/conversations/{$conversation->id}")->assertNotFound();
    $this->postJson("/api/ai-assistant/conversations/{$conversation->id}/messages", ['content' => 'x', 'locale' => 'en'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
    $this->getJson("/api/ai-assistant/messages/{$message->id}/attachments/{$attachment->id}")->assertNotFound();

    expect($conversation->fresh())->not->toBeNull();
});

it('AC-032: keeps the API open when only one surface is on', function (bool $floating, bool $page) {
    config()->set('ai-assistant.floating_enabled', $floating);
    config()->set('ai-assistant.page_enabled', $page);
    Sanctum::actingAs(aiapi_user());

    $this->getJson('/api/ai-assistant/conversations')->assertOk();
})->with([
    'floating only' => [true, false],
    'page only' => [false, true],
]);

it('AC-032: still answers 403 without the permission while the surfaces are off', function () {
    config()->set('ai-assistant.floating_enabled', false);
    config()->set('ai-assistant.page_enabled', false);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/ai-assistant/config')->assertForbidden();
    $this->getJson('/api/ai-assistant/conversations')->assertForbidden();
});

it('AC-033: shows the menu item only while the page is on, keeping the permission in the catalogue', function () {
    $user = aiapi_user();

    config()->set('ai-assistant.page_enabled', false);
    config()->set('ai-assistant.floating_enabled', true);
    expect(aisurf_menu_has_assistant($user))->toBeFalse()
        ->and(app(NavigationService::class)->permissions())->toContain('ai-assistant.use');

    config()->set('ai-assistant.page_enabled', true);
    $item = collect(app(NavigationService::class)->for($user))->firstWhere('key', 'ai-assistant');
    expect($item)->not->toBeNull()
        ->and($item)->not->toHaveKey('feature');
});
