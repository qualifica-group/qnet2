<?php

use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Models\AiMessage;
use App\Models\AiTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const PREVIEW_ID = 'a81f00112233445566778899';

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
    // The delayed job of the expiry: the sync queue of the tests would run it at once.
    Queue::fake([ExpireAiPreview::class]);
});

/** Sends a message whose first model round asks for a write; returns the turn left waiting. */
function writes_suspended_turn(array $modelRounds = [], array $toolResults = [], array $messageData = []): array
{
    $user = aiapi_user();
    aiapi_fake_agent($modelRounds === [] ? [
        aiapi_tool_body('prepare_write', ['{"operation_id":"POST /api/time-entries","body":{}}']),
        aiapi_text_body(['Done.']),
    ] : $modelRounds, $toolResults === [] ? [aiapi_preview_result(PREVIEW_ID)] : $toolResults);

    aiapi_send($user, $conversation = aiapi_conversation($user), $messageData)->assertStatus(202);

    return [AiTurn::query()->latest('id')->firstOrFail(), $user, $conversation];
}

it('AC-004: a valid preview of prepare_write puts the turn in awaiting_confirmation and saves what the connector returned', function () {
    [$turn] = writes_suspended_turn();

    $stored = $turn->fresh();
    expect($stored->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($stored->finished_at)->toBeNull()
        ->and($stored->steps)->toHaveCount(1)
        ->and($stored->steps[0])->toMatchArray(['tool' => 'prepare_write', 'state' => 'done', 'label' => 'Preparing the change'])
        ->and($stored->preview)->toMatchArray([
            'id' => PREVIEW_ID,
            'confirmable' => true,
            'content' => "### Anteprima\n\n**Nuova ora**: crea un'ora.",
            'state' => 'open',
            'outcome' => null,
        ])
        ->and($stored->preview['expires_at'])->toBeString();
    // Only the first model round ran: nothing is left running while the person decides.
    expect(aiapi_sent('chat/completions'))->toHaveCount(1);
    Queue::assertPushed(ExpireAiPreview::class, fn (ExpireAiPreview $job): bool => $job->turnId === $turn->id && $job->previewId === PREVIEW_ID && $job->queue === 'ai-assistant');
});

it('D-17: the preview expires before the connector one, by the configured margin, and the delayed job fires at that instant', function () {
    config()->set('ai-assistant.agent.preview_ttl_seconds', 600);
    config()->set('ai-assistant.agent.preview_margin_seconds', 20);
    $this->freezeSecond();

    [$turn] = writes_suspended_turn();

    $expiresAt = now()->addSeconds(580);
    expect($turn->fresh()->preview['expires_at'])->toBe($expiresAt->toIso8601String());
    Queue::assertPushed(ExpireAiPreview::class, fn (ExpireAiPreview $job): bool => $job->delay !== null && $job->delay->equalTo($expiresAt));
});

it('D-19: the turn state shows the content of the connector, whatever the model wrote about the write', function () {
    [$turn, $user] = writes_suspended_turn([
        aiapi_tool_body('prepare_write', ['{"operation_id":"DELETE /api/clients/1","body":{}}']),
    ], [aiapi_preview_result(PREVIEW_ID, '### Anteprima\n\n**Nuova ora**: 2 ore.')]);

    $state = aiapi_turn_state($turn)->assertOk()
        ->assertJsonPath('data.status', 'awaiting_confirmation')
        ->assertJsonPath('data.preview.id', PREVIEW_ID)
        ->assertJsonPath('data.preview.content', '### Anteprima\n\n**Nuova ora**: 2 ore.')
        ->assertJsonPath('data.preview.confirmable', true)
        ->assertJsonPath('data.preview.state', 'open')
        ->assertJsonPath('data.preview.outcome', null);

    expect(array_keys($state->json('data.preview')))->toBe(['id', 'confirmable', 'content', 'expires_at', 'state', 'outcome'])
        ->and(json_encode($state->json()))->not->toContain('DELETE /api/clients/1');
});

it('keeps what the turn needs to go on: the messages so far, the counters and the call waiting for the result', function () {
    [$turn] = writes_suspended_turn();

    $state = $turn->fresh()->resume_state;
    expect($state['preview_call_id'])->toBe('call_1')
        ->and($state['skipped_call_ids'])->toBe([])
        ->and($state['messages'][0]['role'])->toBe('system')
        ->and(end($state['messages'])['tool_calls'][0]['function']['name'])->toBe('prepare_write')
        ->and($state['model_calls'])->toBe(1)
        ->and($state['tool_calls'])->toBe(1)
        ->and(json_encode($state))->not->toContain(PREVIEW_ID);
});

it('a prepare_write the connector refuses is an ordinary result: the model goes on and no preview is saved', function () {
    $refusal = '{"connettore":{"esito":"fallita","codice":"scritture_sospese"},"dati":null}';
    [$turn] = writes_suspended_turn([
        aiapi_tool_body('prepare_write', ['{}']),
        aiapi_text_body(['Cannot write now.']),
    ], [[$refusal, true]]);

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and($turn->fresh()->preview)->toBeNull();
    Queue::assertNotPushed(ExpireAiPreview::class);
});

it('a model that asks confirm_write gets it discarded, and the connector never sees it', function () {
    [$turn] = writes_suspended_turn([
        aiapi_tool_body('confirm_write', ['{"preview_id":"a81f00112233445566778899"}']),
        aiapi_text_body(['ok']),
    ]);

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Completed);
    $methods = collect(aiapi_sent('connector.test'))->map(fn (array $body): ?string => $body['method'] ?? null)->all();
    expect($methods)->toBe(['tools/list']);
});

it('a tool call after prepare_write in the same round is not run and gets a fixed result on resume', function () {
    $two = aiapi_sse([['model' => 'model-x', 'choices' => [['delta' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
        ['index' => 0, 'id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'prepare_write', 'arguments' => '{}']],
        ['index' => 1, 'id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'prepare_write', 'arguments' => '{}']],
    ]]]]], ['model' => 'model-x', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]]);

    [$turn] = writes_suspended_turn([$two], [aiapi_preview_result(PREVIEW_ID)]);

    expect($turn->fresh()->resume_state)->toMatchArray(['preview_call_id' => 'call_a', 'skipped_call_ids' => ['call_b']])
        ->and(collect(aiapi_sent('connector.test'))->where('method', 'tools/call'))->toHaveCount(1);
});

it('the message of the person stays saved and a second message is refused while the turn waits', function () {
    [$turn, $user, $conversation] = writes_suspended_turn();

    aiapi_send($user, $conversation, ['content' => 'si'])->assertStatus(409);

    expect(AiMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and(collect(aiapi_sent('connector.test'))->pluck('preview_id')->filter()->all())->toBe([]);
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/assistant/confirm'));
});
