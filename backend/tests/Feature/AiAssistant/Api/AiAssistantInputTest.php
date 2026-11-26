<?php

use App\Models\AiMessage;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

it('AC-009: sends the mentioned record as type and identifier in the current turn only and keeps [menzione] in history', function () {
    aiapi_fake_agent([aiapi_text_body(['a']), aiapi_text_body(['b'])]);
    $user = aiapi_super_admin();
    $conversation = aiapi_conversation($user);
    $company = Company::factory()->create(['denomination' => 'Acme Spa', 'vat_number' => '01234567890']);

    // REQUIREMENT CHANGED (spec 0433): the answer is 202 with the turn; the model gets only type and identifier, no field and no label.
    $response = aiapi_send($user, $conversation, [
        'content' => 'Summarize @Acme',
        'mentions' => [['module' => 'companies', 'id' => $company->id]],
    ])->assertStatus(202);

    $current = aiapi_sent('chat/completions')[0]['messages'][1]['content'];
    expect($current)->toContain('<record module="companies" id="'.$company->id.'"/>')
        ->and($current)->not->toContain('01234567890')
        ->and($current)->not->toContain('Acme Spa')
        ->and($response->json('data.user_message.mentions'))->toBe([['module' => 'companies', 'id' => $company->id, 'label' => 'Acme Spa']]);

    $stored = AiMessage::query()->where('role', 'user')->sole();
    expect($stored->mentions)->toBe([['module' => 'companies', 'id' => $company->id, 'label' => 'Acme Spa']]);

    aiapi_send($user, $conversation, ['content' => 'and now?'])->assertStatus(202);

    $history = aiapi_sent('chat/completions')[1]['messages'][1]['content'];
    expect($history)->toContain("[menzione: companies #{$company->id}]")
        ->and($history)->not->toContain('Acme Spa')
        ->and($history)->not->toContain('01234567890')
        ->and($history)->not->toContain('<record');
});

it('AC-010: a mentioned record the user may not view answers 403 and never calls the provider', function () {
    Http::fake();
    $user = aiapi_user(['tasks.view']);
    $conversation = aiapi_conversation($user);
    $foreign = Task::factory()->create();

    aiapi_send($user, $conversation, ['mentions' => [['module' => 'tasks', 'id' => $foreign->id]]])->assertForbidden();

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0);
});

it('AC-010: a missing record answers 422 on mentions.N.id, an unknown module on mentions.N.module', function () {
    Http::fake();
    $user = aiapi_super_admin();
    $conversation = aiapi_conversation($user);
    $company = Company::factory()->create();

    aiapi_send($user, $conversation, ['mentions' => [
        ['module' => 'companies', 'id' => $company->id],
        ['module' => 'companies', 'id' => 999999],
    ]])->assertStatus(422)->assertJsonValidationErrors(['mentions.1.id']);

    aiapi_send($user, $conversation, ['mentions' => [['module' => 'nope', 'id' => 1]]])
        ->assertStatus(422)->assertJsonValidationErrors(['mentions.0.module']);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0);
});

it('AC-012: stores a PDF and an image as attachments, never sends their bytes to the model, and serves the download', function () {
    aiapi_fake_agent([aiapi_text_body(['a'])]);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    // REQUIREMENT CHANGED (spec 0433): the agent model is text-only in the loop; files are kept and listed, not forwarded.
    $response = aiapi_send($user, $conversation, ['files' => [aiapi_pdf('doc.pdf'), aiapi_png('pic.png')]])->assertStatus(202);

    $attachments = Attachment::query()->orderBy('id')->get();
    $userMessage = AiMessage::query()->where('role', 'user')->sole();
    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('attachable_id')->unique()->all())->toBe([$userMessage->id])
        ->and($attachments->pluck('collection')->unique()->all())->toBe(['ai-assistant'])
        ->and($response->json('data.user_message.attachments'))->toHaveCount(2)
        ->and($response->json('data.user_message.attachments.0'))->toHaveKeys(['id', 'original_name', 'mime_type', 'size']);
    foreach ($attachments as $attachment) {
        Storage::disk($attachment->disk)->assertExists($attachment->path);
    }

    $sent = json_encode(aiapi_sent('chat/completions')[0]['messages']);
    expect($sent)->not->toContain('data:application/pdf')->not->toContain('image_url');

    app('auth')->forgetGuards();
    Sanctum::actingAs($user);
    $this->get("/api/ai-assistant/messages/{$userMessage->id}/attachments/{$attachments[0]->id}")->assertOk();
});

it('spec 0433: a text-only model rejects new attachments with a fixed text and keeps the saved ones downloadable', function () {
    aiapi_fake_agent([aiapi_text_body(['a'])]);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    aiapi_send($user, $conversation, ['files' => [aiapi_pdf('doc.pdf')]])->assertStatus(202);
    $message = AiMessage::query()->where('role', 'user')->sole();
    $attachment = Attachment::query()->sole();

    config()->set('ai-assistant.model_accepts_attachments', false);
    Http::fake();

    foreach (['en' => 'The assistant model accepts text only: attachments are not allowed.', 'it' => 'Il modello dell\'assistente accetta solo testo: gli allegati non sono ammessi.'] as $locale => $text) {
        $this->withHeader('Accept-Language', $locale);
        aiapi_send($user, $conversation, ['files' => [aiapi_pdf('new.pdf')]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files'])
            ->assertJsonPath('errors.files.0', $text);
    }
    Http::assertNothingSent();
    expect(Attachment::query()->count())->toBe(1);

    app('auth')->forgetGuards();
    Sanctum::actingAs($user);
    $this->get("/api/ai-assistant/messages/{$message->id}/attachments/{$attachment->id}")->assertOk();
});

it('spec 0433: the config tells the UI whether the model accepts attachments', function () {
    $user = aiapi_user();
    Sanctum::actingAs($user);

    $this->getJson('/api/ai-assistant/config')->assertOk()->assertJsonPath('data.limits.accepts_attachments', true);

    config()->set('ai-assistant.model_accepts_attachments', false);
    $this->getJson('/api/ai-assistant/config')->assertOk()->assertJsonPath('data.limits.accepts_attachments', false);
});

dataset('invalid turns', [
    'file too big' => [fn () => ['files' => [UploadedFile::fake()->createWithContent('big.pdf', '%PDF-1.4'.str_repeat('a', 3000))]], 'files.0', ['ai-assistant.max_file_size_kb' => 1]],
    'too many files' => [fn () => ['files' => [aiapi_pdf('a.pdf'), aiapi_pdf('b.pdf')]], 'files', ['ai-assistant.max_files' => 1]],
    'exe renamed pdf' => [fn () => ['files' => [aiapi_real_upload('evil.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00".str_repeat("\x00", 64))]], 'files.0', []],
    'pdf content with exe extension' => [fn () => ['files' => [aiapi_pdf('evil.exe')]], 'files.0', []],
    'empty content' => [fn () => ['content' => ''], 'content', []],
    'content too long' => [fn () => ['content' => str_repeat('x', 11)], 'content', ['ai-assistant.max_message_chars' => 10]],
    'too many mentions' => [fn () => ['mentions' => [['module' => 'companies', 'id' => 1], ['module' => 'companies', 'id' => 2]]], 'mentions', ['ai-assistant.max_mentions' => 1]],
    'bad locale' => [fn () => ['locale' => 'fr'], 'locale', []],
]);

it('AC-013: rejects invalid input with 422 and writes no row or file', function (Closure $data, string $errorKey, array $config) {
    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }
    Http::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    aiapi_send($user, $conversation, $data())->assertStatus(422)->assertJsonValidationErrors([$errorKey]);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with('invalid turns');
