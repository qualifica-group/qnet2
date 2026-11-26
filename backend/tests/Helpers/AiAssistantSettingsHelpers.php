<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| AI assistant settings helpers (spec 0433)
|--------------------------------------------------------------------------
| Shared by tests/Feature/AiAssistant/Settings/*: parallel runs load only their
| own test files, so a helper used by more than one file lives here.
*/

const AIS_FAKE_KEY = 'sk-or-fake-secret-key-0000';

function ais_super_admin(): User
{
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $user = User::factory()->create(['name' => 'Ada Admin']);
    $user->assignRole('super-admin');

    return $user;
}

/** An OpenRouter /models body: tools+text only are kept; one has images, one lacks tools, one outputs images. */
function ais_models_body(): array
{
    $model = fn (string $id, array $in, array $out, array $params) => [
        'id' => $id,
        'name' => 'Name of '.$id,
        'context_length' => 131072,
        'architecture' => ['input_modalities' => $in, 'output_modalities' => $out],
        'pricing' => ['prompt' => '0.00000014', 'completion' => '0.00000028', 'input_cache_read' => '0.000000014'],
        'supported_parameters' => $params,
    ];

    return ['data' => [
        $model('deepseek/deepseek-v4-flash-0731', ['text'], ['text'], ['tools', 'max_tokens']),
        $model('acme/text-pro', ['text'], ['text'], ['tools']),
        $model('acme/vision', ['text', 'image'], ['text'], ['tools']),
        $model('acme/no-tools', ['text'], ['text'], ['max_tokens']),
        $model('acme/image-out', ['text'], ['text', 'image'], ['tools']),
    ]];
}

function ais_fake_openrouter(mixed $models = null): void
{
    Cache::flush();
    config()->set('ai-assistant.openrouter.base_url', 'https://openrouter.test/api/v1');
    config()->set('ai-assistant.openrouter.api_key', AIS_FAKE_KEY);
    Http::fake(['openrouter.test/api/v1/*' => $models ?? Http::response(ais_models_body())]);
}
