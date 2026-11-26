<?php

use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

/**
 * Re-evaluates config/ai-assistant.php with the given AI_ASSISTANT_ACCEPTED_MIMES value.
 *
 * @return list<string>
 */
function ai_local_accepted_mimes(?string $value): array
{
    $previous = getenv('AI_ASSISTANT_ACCEPTED_MIMES');
    $value === null ? putenv('AI_ASSISTANT_ACCEPTED_MIMES') : putenv("AI_ASSISTANT_ACCEPTED_MIMES={$value}");

    try {
        return (require config_path('ai-assistant.php'))['accepted_mimes'];
    } finally {
        $previous === false ? putenv('AI_ASSISTANT_ACCEPTED_MIMES') : putenv("AI_ASSISTANT_ACCEPTED_MIMES={$previous}");
    }
}

it('AC-029: the env can narrow the accepted types but never add unsupported ones', function () {
    expect(ai_local_accepted_mimes(null))->toBe(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
        ->and(ai_local_accepted_mimes(''))->toBe(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
        ->and(ai_local_accepted_mimes('image/png, image/jpeg'))->toBe(['image/png', 'image/jpeg'])
        ->and(ai_local_accepted_mimes('image/png,application/x-msdownload'))->toBe(['image/png']);
});

it('AC-029: a type removed from the accepted list is rejected with 422 before the provider is called', function () {
    aiapi_model_accepts_attachments();
    config()->set('ai-assistant.accepted_mimes', ['image/png']);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['files' => [aiapi_pdf()]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['files.0']);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0);
});

it('AC-029: with no accepted type any file is refused and the config endpoint says so', function () {
    aiapi_model_accepts_attachments();
    config()->set('ai-assistant.accepted_mimes', []);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['files' => [aiapi_png()]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['files']);

    Http::assertNothingSent();
    Sanctum::actingAs($user);
    $this->getJson('/api/ai-assistant/config')->assertOk()->assertJsonPath('data.limits.accepted_mimes', []);
});
