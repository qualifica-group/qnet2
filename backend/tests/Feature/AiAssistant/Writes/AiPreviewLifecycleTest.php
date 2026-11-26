<?php

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use App\Services\AiAssistant\Preview\AiPreviewDecisions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
    Queue::fake([ExpireAiPreview::class]);
});

function lifecycle_expire(AiTurn $turn, string $previewId = AIAPI_PREVIEW_ID): void
{
    (new ExpireAiPreview($turn->id, $previewId))->handle(app(AiPreviewDecisions::class));
}

function lifecycle_model_calls(): int
{
    return count(aiapi_sent('chat/completions'));
}

it('AC-007: the delayed job resumes a turn nobody answered, with the expired result', function () {
    [$turn] = aiapi_waiting_turn();

    lifecycle_expire($turn);

    $second = aiapi_sent('chat/completions')[1]['messages'];
    $result = json_decode(end($second)['content'], true);
    expect($result['connettore'])->toBe(['esito' => 'fallita', 'codice' => 'anteprima_scaduta', 'testo' => 'The preview expired before it was confirmed. Nothing was written.'])
        ->and(end($second)['tool_call_id'])->toBe('call_1')
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and($turn->fresh()->preview)->toMatchArray(['state' => 'expired', 'outcome' => 'expired']);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/assistant/confirm'));
});

it('AC-007: a second expiry job, and a second resume job, do nothing', function () {
    [$turn] = aiapi_waiting_turn();

    lifecycle_expire($turn);
    lifecycle_expire($turn);
    app(AiTurnRunner::class)->run($turn->id);

    expect(lifecycle_model_calls())->toBe(2)
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::Completed);
});

it('AC-007: the expiry job of an old preview does not resume a turn that waits for another one', function () {
    [$turn] = aiapi_waiting_turn();

    lifecycle_expire($turn, 'bbbbbbbbbbbbbbbbbbbbbbbb');

    expect($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($turn->fresh()->preview['state'])->toBe('open')
        ->and(lifecycle_model_calls())->toBe(1);
});

it('AC-007: the expiry job after the person confirmed does nothing', function () {
    [$turn] = aiapi_waiting_turn();
    aiapi_decide($turn, 'confirm')->assertStatus(202);

    lifecycle_expire($turn);

    expect(lifecycle_model_calls())->toBe(2)
        ->and($turn->fresh()->preview)->toMatchArray(['state' => 'confirmed', 'outcome' => 'succeeded']);
});

it('while a decision is in progress nobody else decides: the second click is refused and nothing changes', function () {
    [$turn] = aiapi_waiting_turn();
    $lock = Cache::lock("ai-assistant:preview-decision:{$turn->id}", 60);
    expect($lock->get())->toBeTrue();

    aiapi_decide($turn, 'cancel')->assertStatus(409)->assertJsonPath('data.reason', 'already_used');
    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'already_used');

    $lock->release();
    expect($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and(Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/assistant/confirm')))->toHaveCount(0);
});

it('AC-004: the answer of the connector decides the outcome the person sees and the model reads', function (string $answer, string $outcome) {
    [$turn] = aiapi_waiting_turn(confirmResults: [$answer]);

    aiapi_decide($turn, 'confirm')->assertStatus(202);

    $second = aiapi_sent('chat/completions')[1]['messages'];
    expect(end($second)['content'])->toBe($answer);
    aiapi_turn_state($turn)->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.preview.state', $outcome === 'succeeded' ? 'confirmed' : $outcome)
        ->assertJsonPath('data.preview.outcome', $outcome);
})->with([
    'refused by QNET2' => ['{"connettore":{"esito":"fallita","codice":"validazione"},"dati":{"testo":"no","codice_http":422}}', 'rejected'],
    'record changed' => ['{"connettore":{"esito":"fallita","codice":"record_cambiato"},"dati":null}', 'stale'],
    'connector preview expired first' => ['{"connettore":{"esito":"fallita","codice":"anteprima_scaduta"},"dati":null}', 'expired'],
    'uncertain' => ['{"connettore":{"esito":"fallita","codice":"esito_incerto"},"dati":{"testo":"verifica"}}', 'uncertain'],
]);

it('an uncertain confirmation is never repeated: one request to the connector, and the person is told by the outcome', function () {
    [$turn] = aiapi_waiting_turn(confirmResults: ['{"connettore":{"esito":"fallita","codice":"esito_incerto"},"dati":null}']);

    aiapi_decide($turn, 'confirm')->assertStatus(202);
    aiapi_decide($turn, 'confirm')->assertStatus(409);

    expect(Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/assistant/confirm')))->toHaveCount(1)
        ->and($turn->fresh()->preview['outcome'])->toBe('uncertain');
});

it('a connector that does not answer leaves the preview open with a fixed text and no repetition', function () {
    [$turn] = aiapi_waiting_turn(confirmStatus: 503);

    aiapi_decide($turn, 'confirm')->assertStatus(503)->assertJsonPath('message', 'The assistant could not reach its tools. Try again later.');

    expect($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($turn->fresh()->preview['state'])->toBe('open')
        ->and(Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/assistant/confirm')))->toHaveCount(1);
});

it('a link the connector rejects closes the turn cancelled and the preview arrives as expired', function () {
    [$turn] = aiapi_waiting_turn(confirmStatus: 401);

    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'expired');

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Cancelled)
        ->and($turn->fresh()->error_code)->toBe(AiTurnErrorCode::LinkLost)
        ->and($turn->fresh()->preview)->toMatchArray(['state' => 'expired', 'outcome' => 'expired']);
});

it('AC-009: a renewed token turns the open preview into an expired one and closes the turn cancelled', function () {
    [$turn, $user, $conversation] = aiapi_waiting_turn();
    PersonalAccessToken::query()->findOrFail($turn->personal_access_token_id)->delete();

    aiapi_turn_state($turn)->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.error_code', 'link_lost')
        ->assertJsonPath('data.error_message', 'Your session changed. Send your message again.')
        ->assertJsonPath('data.preview.state', 'expired')
        ->assertJsonPath('data.preview.outcome', 'expired');

    // The old preview cannot be confirmed any more, and the next message opens a new turn.
    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'expired');
    aiapi_send($user, $conversation)->assertStatus(202);
    expect(Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/assistant/confirm')))->toHaveCount(0);
});

it('AC-009: the confirm click after the token changed does not reach the connector', function () {
    [$turn] = aiapi_waiting_turn();
    PersonalAccessToken::query()->findOrFail($turn->personal_access_token_id)->delete();

    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'expired');

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Cancelled)
        ->and(Http::recorded()->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/assistant/confirm')))->toHaveCount(0);
});

it('the expiry job finds the link gone and closes the turn cancelled instead of resuming it', function () {
    [$turn] = aiapi_waiting_turn();
    PersonalAccessToken::query()->findOrFail($turn->personal_access_token_id)->delete();

    lifecycle_expire($turn);

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Cancelled)
        ->and(lifecycle_model_calls())->toBe(1);
});

it('D-3: a second conversation of the same session cannot prepare a write while the first waits', function () {
    $user = aiapi_user();
    aiapi_fake_agent([
        aiapi_tool_body('prepare_write', ['{}']),
        aiapi_tool_body('prepare_write', ['{}']),
        aiapi_text_body(['Another preview is open.']),
    ], [aiapi_preview_result(AIAPI_PREVIEW_ID)]);
    $header = ['Authorization' => 'Bearer '.$user->createToken('web')->plainTextToken];

    foreach ([aiapi_conversation($user), aiapi_conversation($user)] as $conversation) {
        app('auth')->forgetGuards();
        $this->withHeaders($header)->post("/api/ai-assistant/conversations/{$conversation->id}/messages", ['content' => 'Change it', 'locale' => 'en'], ['Accept' => 'application/json'])->assertStatus(202);
    }

    [$first, $second] = AiTurn::query()->orderBy('id')->get()->all();
    $busy = json_decode(end(aiapi_sent('chat/completions')[2]['messages'])['content'], true);
    expect($first->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($second->status)->toBe(AiTurnStatus::Completed)
        ->and($second->preview)->toBeNull()
        ->and($busy['connettore'])->toBe(['esito' => 'fallita', 'codice' => 'anteprima_in_attesa', 'testo' => 'Another preview is already waiting for your confirmation.'])
        // The connector got one prepare_write only: the second never left QNET2.
        ->and(collect(aiapi_sent('connector.test'))->where('method', 'tools/call'))->toHaveCount(1);
});

it('the time spent before the wait counts, the wait itself does not: a resumed turn past its cap closes timed out', function () {
    [$turn] = aiapi_waiting_turn();
    $state = $turn->fresh()->resume_state;
    $state['elapsed_seconds'] = (int) config('ai-assistant.agent.max_turn_seconds') + 1;
    $turn->update(['resume_state' => $state]);

    aiapi_decide($turn, 'confirm')->assertStatus(202);

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Failed)
        ->and($turn->fresh()->error_code)->toBe(AiTurnErrorCode::TurnTimeout);
});

it('the caps of the turn go on across the wait: the counters of model and tool calls carry over', function () {
    config()->set('ai-assistant.agent.max_model_calls', 2);
    [$turn] = aiapi_waiting_turn(modelRounds: [aiapi_tool_body('list_records', ['{}']), aiapi_text_body(['never'])]);

    aiapi_decide($turn, 'cancel')->assertStatus(202);

    expect($turn->fresh()->status)->toBe(AiTurnStatus::Failed)
        ->and($turn->fresh()->error_code)->toBe(AiTurnErrorCode::TooManySteps);
});
