<?php

use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\ExpireAiPreview;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
    Queue::fake([ExpireAiPreview::class]);
});

/** Requests of the connector to `/assistant/confirm`. */
function decision_confirm_requests(): array
{
    return Http::recorded()->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_ends_with($request->url(), '/assistant/confirm'))->values()->all();
}

it('AC-004: confirm calls the confirmation route of the connector with the identifier only and the turn goes on with the answer', function () {
    $answer = '{"connettore":{"esito":"riuscita"},"dati":{"testo":"Scritto.","codice_http":201,"risposta":{"id":7}}}';
    [$turn] = aiapi_waiting_turn(confirmResults: [$answer]);

    aiapi_decide($turn, 'confirm')->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.preview.id', AIAPI_PREVIEW_ID);

    // The confirmation: connector route, secret, link credential, body of the identifier alone.
    $requests = decision_confirm_requests();
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->url())->toBe(AIAPI_CONNECTOR_URL.'/assistant/confirm')
        ->and($requests[0]->body())->toBe('{"preview_id":"'.AIAPI_PREVIEW_ID.'"}')
        ->and($requests[0]->header('x-assistant-secret'))->toBe([AIAPI_CONNECTOR_SECRET])
        ->and($requests[0]->header('x-assistant-link-credential')[0])->toMatch('/^[0-9a-f]{64}$/');

    // With the sync queue the resumed turn has run: the model got the connector answer as the result of its call.
    $second = aiapi_sent('chat/completions')[1]['messages'];
    expect(end($second))->toBe(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => $answer]);

    $final = aiapi_turn_state($turn)->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.assistant_text', 'Done.')
        ->assertJsonPath('data.preview.state', 'confirmed')
        ->assertJsonPath('data.preview.outcome', 'succeeded');
    expect($turn->fresh()->resume_state)->toBeNull()
        ->and($turn->fresh()->usage)->toMatchArray(['model_calls' => 2, 'tool_calls' => 1])
        ->and($final->json('data.steps'))->toHaveCount(1);
});

it('confirm answers 202 with the turn queued and the preview confirmed while the resume job waits in the queue', function () {
    [$turn] = aiapi_waiting_turn();
    Queue::fake();

    $response = aiapi_decide($turn, 'confirm')->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.preview.state', 'confirmed')
        // REQUIREMENT CHANGED (review of the turn): the outcome of the connector is saved at the answer, not when the turn resumes.
        ->assertJsonPath('data.preview.outcome', 'succeeded');

    Queue::assertPushedOn('ai-assistant', RunAiTurn::class, fn (RunAiTurn $job): bool => $job->turnId === $turn->id);
    expect($response->json('data.queue_position'))->toBe(1);
});

it('AC-005: cancel writes nothing and the turn goes on with the cancelled result', function () {
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'cancel')->assertStatus(202)->assertJsonPath('data.preview.state', 'cancelled');

    expect(decision_confirm_requests())->toBe([]);
    $second = aiapi_sent('chat/completions')[1]['messages'];
    $result = json_decode(end($second)['content'], true);
    expect(end($second)['tool_call_id'])->toBe('call_1')
        ->and($result['connettore'])->toBe(['esito' => 'fallita', 'codice' => 'anteprima_annullata', 'testo' => 'You cancelled the preview. Nothing was written.']);
    aiapi_turn_state($turn)->assertJsonPath('data.status', 'completed')->assertJsonPath('data.preview.outcome', 'cancelled');
});

it('the result handed to the model is in the language of the turn', function () {
    $user = aiapi_user();
    aiapi_fake_agent([aiapi_tool_body('prepare_write', ['{}']), aiapi_text_body(['Fatto.'])], [aiapi_preview_result(AIAPI_PREVIEW_ID)]);
    aiapi_send($user, aiapi_conversation($user), ['locale' => 'it'])->assertStatus(202);
    $turn = AiTurn::query()->latest('id')->firstOrFail();

    aiapi_decide($turn, 'cancel')->assertStatus(202);

    $second = aiapi_sent('chat/completions')[1]['messages'];
    expect(json_decode(end($second)['content'], true)['connettore']['testo'])->toBe("Hai annullato l'anteprima. Non è stato scritto nulla.");
});

it('the round after the confirmation also answers the calls that were left out of the same round', function () {
    $two = aiapi_sse([['model' => 'model-x', 'choices' => [['delta' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
        ['index' => 0, 'id' => 'call_a', 'type' => 'function', 'function' => ['name' => 'prepare_write', 'arguments' => '{}']],
        ['index' => 1, 'id' => 'call_b', 'type' => 'function', 'function' => ['name' => 'list_records', 'arguments' => '{}']],
    ]]]]], ['model' => 'model-x', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']]]]);
    $user = aiapi_user();
    aiapi_fake_agent([$two, aiapi_text_body(['ok'])], [aiapi_preview_result(AIAPI_PREVIEW_ID)]);
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    $turn = AiTurn::query()->latest('id')->firstOrFail();

    aiapi_decide($turn, 'cancel')->assertStatus(202);

    $tail = array_slice(aiapi_sent('chat/completions')[1]['messages'], -2);
    expect(array_column($tail, 'tool_call_id'))->toBe(['call_a', 'call_b'])
        ->and(json_decode($tail[1]['content'], true)['connettore']['codice'])->toBe('non_eseguita');
});

it('AC-006: another person is refused and the connector is not called', function () {
    $other = aiapi_user();
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'confirm', $other)->assertStatus(403);
    aiapi_decide($turn, 'cancel', $other)->assertStatus(403);

    expect(decision_confirm_requests())->toBe([])
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation);
});

it('AC-006: the turn of another conversation is not found under this one', function () {
    [$turn, $user] = aiapi_waiting_turn();
    $elsewhere = aiapi_conversation($user);

    aiapi_decide($turn, 'confirm', url: "/api/ai-assistant/conversations/{$elsewhere->id}/turns/{$turn->id}/confirm")->assertStatus(404);

    expect(decision_confirm_requests())->toBe([]);
});

it('AC-006: a preview that is not the one of the turn is refused with a fixed text, whoever wrote the identifier', function () {
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'confirm', body: ['preview_id' => 'ffffffffffffffffffffffff'])->assertStatus(404)
        ->assertJsonPath('message', 'Preview not found.');

    expect(decision_confirm_requests())->toBe([]);
});

it('the identifier of the preview is validated: required, a string, at most 128 characters', function (array $body) {
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'confirm', body: $body)->assertStatus(422)->assertJsonValidationErrors('preview_id');
})->with([
    'missing' => [[]],
    'not a string' => [['preview_id' => ['x']]],
    'too long' => [['preview_id' => str_repeat('a', 129)]],
]);

it('AC-006: a preview used twice answers 409 already_used and the connector is called once', function () {
    [$turn] = aiapi_waiting_turn();

    aiapi_decide($turn, 'confirm')->assertStatus(202);
    aiapi_decide($turn, 'confirm')->assertStatus(409)
        ->assertJsonPath('message', 'This preview is no longer open.')
        ->assertJsonPath('data.reason', 'already_used');
    aiapi_decide($turn, 'cancel')->assertStatus(409)->assertJsonPath('data.reason', 'already_used');

    expect(decision_confirm_requests())->toHaveCount(1);
});

it('AC-006: a preview past its time answers 409 expired, nothing is written, and the turn goes on as expired', function () {
    [$turn] = aiapi_waiting_turn();
    $this->travel(11)->minutes();

    aiapi_decide($turn, 'confirm')->assertStatus(409)->assertJsonPath('data.reason', 'expired');

    expect(decision_confirm_requests())->toBe([]);
    $second = aiapi_sent('chat/completions')[1]['messages'];
    expect(json_decode(end($second)['content'], true)['connettore']['codice'])->toBe('anteprima_scaduta');
    aiapi_turn_state($turn)->assertJsonPath('data.preview.state', 'expired')->assertJsonPath('data.preview.outcome', 'expired');
});

it('a preview the connector marked not confirmable is refused by the server, and can still be cancelled', function () {
    [$turn] = aiapi_waiting_turn(previewResult: aiapi_preview_result(AIAPI_PREVIEW_ID, 'Missing fields', confirmable: false));

    aiapi_decide($turn, 'confirm')->assertStatus(409)
        ->assertJsonPath('message', 'This preview cannot be confirmed.')
        ->assertJsonPath('data.reason', 'not_confirmable');
    expect(decision_confirm_requests())->toBe([])
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation);

    aiapi_decide($turn, 'cancel')->assertStatus(202);
});

it('without the permission or with the surfaces off the decision routes do not answer', function () {
    [$turn, $user] = aiapi_waiting_turn();

    app('auth')->forgetGuards();
    $this->flushHeaders()->postJson(aiapi_turn_url($turn, '/confirm'), ['preview_id' => AIAPI_PREVIEW_ID])->assertStatus(401);

    auth()->shouldUse('web');
    $user->revokePermissionTo('ai-assistant.use');
    aiapi_decide($turn, 'confirm', $user->fresh())->assertStatus(403);

    expect(decision_confirm_requests())->toBe([]);
});
