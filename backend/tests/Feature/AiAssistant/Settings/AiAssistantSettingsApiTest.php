<?php

use App\Models\AiAssistantSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

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

it('AC-002: answers 403 to every non super-admin, even with every permission, and 401 anonymous', function () {
    $this->getJson('/api/ai-assistant/settings')->assertUnauthorized();

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ai-assistant.use'));
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-assistant/settings')->assertForbidden();
    $this->putJson('/api/ai-assistant/settings', ['enabled' => false])->assertForbidden();
    $this->getJson('/api/ai-assistant/models')->assertForbidden();
    $this->getJson('/api/ai-assistant/models/providers?model_id=acme/text-pro')->assertForbidden();
    expect(AiAssistantSetting::query()->count())->toBe(0);
});

it('GET settings creates the row with the initial values and the seven-part instructions', function () {
    ais_fake_openrouter();
    Sanctum::actingAs(ais_super_admin());

    $data = $this->getJson('/api/ai-assistant/settings')->assertOk()->json('data');

    expect($data['enabled'])->toBeTrue()
        ->and($data['model_id'])->toBe('deepseek/deepseek-v4-flash-0731')
        ->and($data['routing'])->toBe(['zdr' => true, 'data_collection' => 'deny', 'require_parameters' => true, 'sort' => 'throughput'])
        ->and($data['instructions_max_chars'])->toBeInt()->toBeGreaterThan(1000)
        ->and($data['openrouter_key_present'])->toBeTrue()
        ->and($data['updated_by'])->toBeNull()
        ->and(mb_strlen($data['instructions']))->toBeLessThanOrEqual($data['instructions_max_chars']);

    foreach (['## Ruolo', '## Vincoli', '## Contesto', '## Formato', '## Esempi', '## Risultati da evitare', '## Criteri di riuscita'] as $part) {
        expect($data['instructions'])->toContain($part);
    }
    expect(json_encode($data))->not->toContain(AIS_FAKE_KEY);
});

it('reports the key as absent when the server has none, never its value', function () {
    ais_fake_openrouter();
    config()->set('ai-assistant.openrouter.api_key', null);
    Sanctum::actingAs(ais_super_admin());

    $this->getJson('/api/ai-assistant/settings')->assertOk()->assertJsonPath('data.openrouter_key_present', false);
});

it('AC-014: PUT saves, records author and date in the activity log and exposes updated_by', function () {
    ais_fake_openrouter();
    $admin = ais_super_admin();
    Sanctum::actingAs($admin);
    $this->getJson('/api/ai-assistant/settings')->assertOk();

    $response = $this->putJson('/api/ai-assistant/settings', [
        'enabled' => false,
        'model_id' => 'acme/text-pro',
        'instructions' => 'Rispondi sempre in modo breve.',
        'spend_cap_person_usd' => '5.50',
        'spend_cap_total_usd' => null,
        'routing' => ['zdr' => false, 'data_collection' => 'allow', 'require_parameters' => false, 'sort' => 'price'],
    ])->assertOk();

    $response->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.model_id', 'acme/text-pro')
        ->assertJsonPath('data.instructions', 'Rispondi sempre in modo breve.')
        ->assertJsonPath('data.spend_cap_person_usd', '5.50')
        ->assertJsonPath('data.routing.sort', 'price')
        ->assertJsonPath('data.updated_by.id', $admin->id)
        ->assertJsonPath('data.updated_by.name', 'Ada Admin');
    expect($response->json('data.updated_at'))->not->toBeNull();

    $activity = Activity::query()->where('subject_type', (new AiAssistantSetting)->getMorphClass())->where('event', 'updated')->latest('id')->first();
    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->created_at)->not->toBeNull()
        ->and($activity->properties['attributes']['model_id'])->toBe('acme/text-pro')
        ->and($activity->properties['attributes']['instructions'])->toBe('Rispondi sempre in modo breve.')
        ->and(json_encode(Activity::query()->get()->toArray()))->not->toContain(AIS_FAKE_KEY);
});

it('AC-014: PUT rejects a model outside the filtered list with the fixed text and writes nothing', function () {
    ais_fake_openrouter();
    Sanctum::actingAs(ais_super_admin());
    $this->getJson('/api/ai-assistant/settings')->assertOk();

    foreach (['acme/vision', 'acme/no-tools', 'nobody/unknown'] as $modelId) {
        $this->putJson('/api/ai-assistant/settings', ['model_id' => $modelId])
            ->assertUnprocessable()->assertJsonValidationErrors('model_id')
            ->assertJsonPath('errors.model_id.0', 'The selected model is not available.');
    }
    expect(AiAssistantSetting::query()->first()->model_id)->toBe('deepseek/deepseek-v4-flash-0731');
});

it('PUT validates instructions length, negative caps and routing values', function () {
    ais_fake_openrouter();
    Sanctum::actingAs(ais_super_admin());
    $max = (int) config('ai-assistant.settings.instructions_max_chars');

    $this->putJson('/api/ai-assistant/settings', [
        'instructions' => str_repeat('a', $max + 1),
        'spend_cap_person_usd' => '-1',
        'routing' => ['sort' => 'random', 'data_collection' => 'maybe'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['instructions', 'spend_cap_person_usd', 'routing.sort', 'routing.data_collection']);
});

it('PUT keeps the saved model without asking OpenRouter, so the switch works while it is down', function () {
    ais_fake_openrouter(Http::response('boom', 500));
    Sanctum::actingAs(ais_super_admin());

    $this->putJson('/api/ai-assistant/settings', ['enabled' => false])
        ->assertOk()->assertJsonPath('data.enabled', false);
    Http::assertNothingSent();
});

it('PUT of a changed model answers 503 with the fixed text when OpenRouter is down', function () {
    ais_fake_openrouter(Http::response('boom', 500));
    Sanctum::actingAs(ais_super_admin());

    $this->putJson('/api/ai-assistant/settings', ['model_id' => 'acme/text-pro'])
        ->assertStatus(503)->assertJsonPath('message', 'The model list is not available. Try again later.');
});
