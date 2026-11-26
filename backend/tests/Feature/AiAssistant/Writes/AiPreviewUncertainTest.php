<?php

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiMessage;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use App\Services\AiAssistant\Preview\AiPreviewDecisions;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/*
| REQUIREMENT CHANGED (review of the turn): a confirmation whose answer was lost AFTER the request left
| QNET2 (timeout, unreadable answer, 5xx) is not "unreachable, try again": the write may have happened,
| so the preview becomes `uncertain`, it can not be clicked again, and the turn goes on with that outcome.
| Only a failure BEFORE any byte was sent (connection refused, 4xx) still means "try again".
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
    Queue::fake([ExpireAiPreview::class]);
});

/** @return list<Request> */
function uncertain_confirm_requests(): array
{
    return Http::recorded()->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_ends_with($request->url(), '/assistant/confirm'))->values()->all();
}

/** A connector that never answers the confirmation after reading it (cURL error 28 with the request already sent). */
function uncertain_timeout(): Closure
{
    return fn (Request $request) => throw new ConnectException('cURL error 28: Operation timed out', $request->toPsrRequest(), null, ['errno' => 28, 'pretransfer_time' => 0.2]);
}

/** A connector that refuses the connection: no byte left QNET2. */
function uncertain_refused(): Closure
{
    return fn (Request $request) => throw new ConnectException('cURL error 7: Failed to connect', $request->toPsrRequest(), null, ['errno' => 7, 'pretransfer_time' => 0.0]);
}

function uncertain_tool_result(array $messages): array
{
    return json_decode(end($messages)['content'], true);
}

it('a timeout after the request was sent makes the preview uncertain, the turn goes on, and the model is told to read the record again', function () {
    [$turn] = aiapi_waiting_turn(confirmResponder: uncertain_timeout());

    aiapi_decide($turn, 'confirm')->assertStatus(202)
        ->assertJsonPath('data.preview.state', 'uncertain')
        ->assertJsonPath('data.preview.outcome', 'uncertain');

    $result = uncertain_tool_result(aiapi_sent('chat/completions')[1]['messages']);
    expect($result['connettore']['codice'])->toBe('esito_incerto')
        ->and($result['connettore']['esito'])->toBe('fallita')
        ->and($result['connettore']['testo'])->toContain('Do not repeat the change')->toContain('Read the record again')
        ->and($result['dati'])->toBeNull()
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and(uncertain_confirm_requests())->toHaveCount(1);
});

it('the text for the model and the person is fixed and in the language of the turn', function () {
    $user = aiapi_user();
    aiapi_fake_agent([aiapi_tool_body('prepare_write', ['{}']), aiapi_text_body(['Verificato.'])], [aiapi_preview_result(AIAPI_PREVIEW_ID)], confirmResponder: uncertain_timeout());
    aiapi_send($user, aiapi_conversation($user), ['locale' => 'it'])->assertStatus(202);
    $turn = AiTurn::query()->latest('id')->firstOrFail();

    aiapi_decide($turn, 'confirm')->assertStatus(202);

    expect(uncertain_tool_result(aiapi_sent('chat/completions')[1]['messages'])['connettore']['testo'])->toContain('Non ripetere la modifica');
});

it('an answer that is not readable, or a 5xx, after the request was sent is uncertain too', function (string $body, int $status) {
    [$turn] = aiapi_waiting_turn(confirmResponder: fn () => Http::response($body, $status));

    aiapi_decide($turn, 'confirm')->assertStatus(202)->assertJsonPath('data.preview.outcome', 'uncertain');

    expect(uncertain_confirm_requests())->toHaveCount(1)
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::Completed);
})->with([
    'server error' => ['', 503],
    'server error with a body' => ['{"error":"boom"}', 500],
    'not json' => ['<html>oops</html>', 200],
    'json without envelope' => ['{"x":1}', 200],
]);

it('after an uncertain outcome nobody can click again: the second click is already_used, never rejected, and nothing is sent twice', function () {
    [$turn] = aiapi_waiting_turn(confirmResponder: uncertain_timeout());

    aiapi_decide($turn, 'confirm')->assertStatus(202);
    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'already_used');
    aiapi_decide($turn, 'cancel')->assertStatus(409)->assertJsonPath('data.reason', 'already_used');

    expect(uncertain_confirm_requests())->toHaveCount(1)
        ->and($turn->fresh()->preview)->toMatchArray(['state' => 'uncertain', 'outcome' => 'uncertain']);
});

it('a failure before any byte was sent, or a 4xx, still means try again: the preview stays open and a new click works', function (Closure $responder) {
    [$turn] = aiapi_waiting_turn(confirmResponder: $responder);

    aiapi_decide($turn, 'confirm')->assertStatus(503)->assertJsonPath('message', 'The assistant could not reach its tools. Try again later.');

    expect($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation)
        ->and($turn->fresh()->preview['state'])->toBe('open')
        ->and($turn->fresh()->resume_state)->not->toHaveKey('confirming');
})->with([
    'connection refused' => [fn () => uncertain_refused()],
    'too many requests' => [fn () => fn () => Http::response('', 429)],
]);

it('the click after a refused connection goes through', function () {
    $calls = 0;
    [$turn] = aiapi_waiting_turn(confirmResponder: function (Request $request) use (&$calls) {
        return ++$calls === 1
            ? throw new ConnectException('cURL error 7', $request->toPsrRequest(), null, ['errno' => 7])
            : Http::response('{"connettore":{"esito":"riuscita"},"dati":{"testo":"ok"}}', 200);
    });

    aiapi_decide($turn, 'confirm')->assertStatus(503);
    aiapi_decide($turn, 'confirm')->assertStatus(202)->assertJsonPath('data.preview.outcome', 'succeeded');
});

it('a worker that died while the confirmation was in flight leaves a mark: the next click is uncertain and the connector is not called again', function () {
    [$turn] = aiapi_waiting_turn(confirmResponder: fn () => Http::response('{"connettore":{"esito":"riuscita"},"dati":[]}', 200));
    $turn->update(['resume_state' => [...$turn->fresh()->resume_state, 'confirming' => true]]);

    aiapi_decide($turn, 'confirm')->assertStatus(202)->assertJsonPath('data.preview.outcome', 'uncertain');

    expect(uncertain_confirm_requests())->toBe([]);
});

it('the expiry job of a preview whose confirmation was in flight resumes the turn as uncertain, not as expired', function () {
    [$turn] = aiapi_waiting_turn();
    $turn->update(['resume_state' => [...$turn->fresh()->resume_state, 'confirming' => true]]);

    (new ExpireAiPreview($turn->id, AIAPI_PREVIEW_ID))->handle(app(AiPreviewDecisions::class));

    expect($turn->fresh()->preview)->toMatchArray(['state' => 'uncertain', 'outcome' => 'uncertain']);
});

it('the outcome is saved on the preview at the moment of the answer, before the turn resumes', function () {
    [$turn] = aiapi_waiting_turn();
    Queue::fake();

    aiapi_decide($turn, 'confirm')->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.preview.state', 'confirmed')
        ->assertJsonPath('data.preview.outcome', 'succeeded');

    Queue::assertPushedOn('ai-assistant', RunAiTurn::class);
});

it('a turn that cannot resume closes with a fixed assistant message that reports the outcome', function (Closure $breakIt, AiTurnErrorCode $code) {
    [$turn] = aiapi_waiting_turn();
    Queue::fake();
    aiapi_decide($turn, 'confirm')->assertStatus(202);
    $breakIt($turn);

    app(AiTurnRunner::class)->run($turn->id);

    $closed = $turn->fresh();
    expect($closed->status)->not->toBe(AiTurnStatus::Completed)
        ->and($closed->error_code)->toBe($code)
        ->and($closed->assistant_text)->toContain('was confirmed and written')
        ->and($closed->assistant_message_id)->not->toBeNull()
        ->and(AiMessage::query()->find($closed->assistant_message_id)->content)->toBe($closed->assistant_text)
        ->and($closed->resume_state)->toBeNull()
        ->and($closed->preview)->toMatchArray(['state' => 'confirmed', 'outcome' => 'succeeded'])
        ->and(count($closed->steps))->toBe(1);
})->with([
    'switched off' => [function (AiTurn $turn) {
        app(AiAssistantSettingsService::class)->current()->update(['enabled' => false]);
    }, AiTurnErrorCode::SwitchedOff],
    'time cap' => [function (AiTurn $turn) {
        $turn->update(['resume_state' => [...$turn->fresh()->resume_state, 'elapsed_seconds' => (int) config('ai-assistant.agent.max_turn_seconds') + 1]]);
    }, AiTurnErrorCode::TurnTimeout],
    'session token gone' => [function (AiTurn $turn) {
        PersonalAccessToken::query()->findOrFail($turn->personal_access_token_id)->delete();
    }, AiTurnErrorCode::LinkLost],
]);

it('the saved state of the wait is spent only when the resumed turn has saved its result: a model that fails still leaves the outcome', function () {
    $broken = aiapi_sse([['model' => 'model-x', 'choices' => [['delta' => ['content' => 'half']]]]]);
    [$turn] = aiapi_waiting_turn(modelRounds: [$broken]);

    aiapi_decide($turn, 'confirm')->assertStatus(202);

    $closed = $turn->fresh();
    expect($closed->status)->toBe(AiTurnStatus::Failed)
        ->and($closed->error_code)->toBe(AiTurnErrorCode::StreamError)
        ->and($closed->assistant_text)->toContain('was confirmed and written')
        ->and($closed->resume_state)->toBeNull();
});

it('the resume of a completed turn leaves no saved state behind', function () {
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'confirm')->assertStatus(202);

    expect($turn->fresh()->resume_state)->toBeNull()->and($turn->fresh()->status)->toBe(AiTurnStatus::Completed);
});

it('while another prepare_write of the link is in flight the second one is answered busy, without a call to the connector', function () {
    $user = aiapi_user();
    $token = $user->createToken('web');
    aiapi_fake_agent([aiapi_tool_body('prepare_write', ['{}']), aiapi_text_body(['busy answered'])], [aiapi_preview_result(AIAPI_PREVIEW_ID)]);
    $lock = Cache::lock("ai-assistant:prepare-write:{$token->accessToken->id}", 60);
    expect($lock->get())->toBeTrue();

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)->post(
        '/api/ai-assistant/conversations/'.aiapi_conversation($user)->id.'/messages',
        ['content' => 'Change it', 'locale' => 'en'],
        ['Accept' => 'application/json'],
    )->assertStatus(202);

    $busy = json_decode(end(aiapi_sent('chat/completions')[1]['messages'])['content'], true);
    expect($busy['connettore']['codice'])->toBe('anteprima_in_attesa')
        ->and(collect(aiapi_sent('connector.test'))->where('method', 'tools/call'))->toHaveCount(0)
        ->and(AiTurn::query()->latest('id')->firstOrFail()->preview)->toBeNull();

    $lock->release();
});

it('the link lock is released after the preview was saved, so the same link can prepare another write once the first is decided', function () {
    [$turn] = aiapi_waiting_turn();
    $lock = Cache::lock("ai-assistant:prepare-write:{$turn->personal_access_token_id}", 5);

    expect($lock->get())->toBeTrue();
    $lock->release();
});
