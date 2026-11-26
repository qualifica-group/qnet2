<?php

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiAssistantSetting;
use App\Models\AiMessage;
use App\Models\AiTurn;
use App\Models\Company;
use App\Models\User;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    aiapi_configure();
});

/** Sends a message and returns [response, turn]; with the sync queue the turn has already run. */
function turn_send(?User $user = null, array $data = []): array
{
    $user ??= aiapi_user();
    $response = aiapi_send($user, aiapi_conversation($user), $data);

    return [$response, AiTurn::query()->latest('id')->first(), $user];
}

/** GET as the given user, with a fresh token and no guard left over from a previous request. */
function turn_get(User $user, string $url)
{
    app('auth')->forgetGuards();

    return test()->withHeader('Authorization', 'Bearer '.$user->createToken('read')->plainTextToken)->getJson($url);
}

function turn_state(AiTurn $turn)
{
    return turn_get($turn->conversation->user, "/api/ai-assistant/conversations/{$turn->ai_conversation_id}/turns/{$turn->id}");
}

it('AC-003: answers 202 with the saved message and a queued turn on the ai-assistant queue', function () {
    Queue::fake();
    $user = aiapi_user();

    $response = aiapi_send($user, aiapi_conversation($user), ['content' => 'Ciao'])->assertStatus(202)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user_message.content', 'Ciao')
        ->assertJsonPath('data.turn.status', 'queued')
        ->assertJsonPath('data.turn.queue_position', 1)
        ->assertJsonPath('data.turn.assistant_text', '')
        ->assertJsonPath('data.turn.steps', []);

    Queue::assertPushedOn('ai-assistant', RunAiTurn::class);
    expect(AiMessage::query()->count())->toBe(1)
        ->and(AiTurn::query()->sole()->id)->toBe($response->json('data.turn.id'));
    Http::assertNothingSent();
});

it('AC-003: an answer in text closes the turn completed and stores the assistant message', function () {
    aiapi_fake_agent([aiapi_text_body(['Ci', 'ao', '!'])]);

    [$response, $turn] = turn_send(data: ['content' => 'Ciao assistente']);

    $response->assertStatus(202);
    $state = turn_state($turn)->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.assistant_text', 'Ciao!')
        ->assertJsonPath('data.assistant_message.content', 'Ciao!')
        ->assertJsonPath('data.assistant_message.role', 'assistant')
        ->assertJsonPath('data.error_code', null)
        ->assertJsonPath('data.preview', null);

    $stored = $turn->fresh();
    expect($state->json('data.finished_at'))->not->toBeNull()
        ->and($stored->usage)->toMatchArray(['model_calls' => 1, 'tool_calls' => 0, 'input_tokens' => 100, 'output_tokens' => 20, 'cached_tokens' => 40, 'reasoning_tokens' => 5, 'cost_usd' => '0.001200'])
        ->and(AiMessage::query()->where('role', 'assistant')->sole()->only(['model', 'input_tokens', 'output_tokens']))->toBe(['model' => 'model-x', 'input_tokens' => 100, 'output_tokens' => 20])
        ->and($turn->conversation->fresh()->title)->toBe('Ciao assistente')
        ->and($turn->conversation->fresh()->last_message_at)->not->toBeNull();
});

it('AC-003: a list in one tool call: the model asks, the connector answers with the person link, the turn completes', function () {
    $envelope = '{"connettore":{"esito":"riuscita"},"dati":[{"code":"COM-1"}]}';
    aiapi_fake_agent([
        aiapi_tool_body('list_records', ['{"domain":', '"work-orders"}']),
        aiapi_text_body(['Hai una commessa: COM-1.']),
    ], [$envelope]);

    [, $turn] = turn_send(data: ['content' => 'mostrami le mie commesse aperte']);

    turn_state($turn)->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.assistant_text', 'Hai una commessa: COM-1.')
        ->assertJsonPath('data.steps.0.tool', 'list_records')
        ->assertJsonPath('data.steps.0.state', 'done')
        ->assertJsonPath('data.steps.0.label', 'Reading the records');

    // The connector got the shared secret, the credential of the link of this person and the arguments as the model wrote them.
    $calls = Http::recorded()->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'connector.test'))->map(fn (array $pair) => $pair[0])->values();
    /** @var Request $call */
    $call = $calls->last();
    expect($calls->map(fn (Request $r): string => $r->data()['method'])->all())->toBe(['tools/list', 'tools/call'])
        ->and($call->url())->toBe(AIAPI_CONNECTOR_URL.'/assistant/mcp')
        ->and($call->header('x-assistant-secret'))->toBe([AIAPI_CONNECTOR_SECRET])
        ->and($call->header('x-assistant-link-credential')[0])->toMatch('/^[0-9a-f]{64}$/')
        ->and($call->header('Host'))->toBe(['connector.public.test'])
        ->and($call->header('Accept'))->toBe(['application/json, text/event-stream'])
        ->and($call->body())->toContain('"name":"list_records"')->toContain('"arguments":{"domain":"work-orders"}');

    // The second model round carries the assistant tool call and the connector result untouched.
    $second = aiapi_sent('chat/completions')[1]['messages'];
    expect(end($second))->toBe(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => $envelope])
        ->and($second[count($second) - 2]['tool_calls'][0]['function'])->toBe(['name' => 'list_records', 'arguments' => '{"domain":"work-orders"}']);
    expect($turn->fresh()->usage)->toMatchArray(['model_calls' => 2, 'tool_calls' => 1, 'input_tokens' => 150, 'cost_usd' => '0.001700']);
});

it('sends the reasoning blocks back unchanged after a tool call', function () {
    $details = [['type' => 'reasoning.encrypted', 'data' => 'abc', 'id' => 'r1', 'index' => 0], ['type' => 'reasoning.text', 'text' => 'hm', 'signature' => 'sg', 'index' => 1]];
    aiapi_fake_agent([aiapi_tool_body('search_operations', ['{}'], reasoningDetails: $details), aiapi_text_body(['ok'])]);

    [, $turn] = turn_send();

    $assistant = aiapi_sent('chat/completions')[1]['messages'];
    expect($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and($assistant[count($assistant) - 2]['reasoning_details'])->toBe($details);
});

it('AC-005: a tool name outside the allowed list is discarded and never reaches the connector', function (string $name) {
    aiapi_fake_agent([aiapi_tool_body($name, ['{"preview_id":"abc"}']), aiapi_text_body(['no'])], withConfirm: true);

    [, $turn] = turn_send();

    $methods = collect(aiapi_sent('connector.test'))->pluck('method')->all();
    $messages = aiapi_sent('chat/completions')[1]['messages'];
    expect($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and($methods)->toBe(['tools/list'])
        ->and($turn->fresh()->steps)->toBeNull()
        ->and(end($messages)['role'])->toBe('tool')
        ->and(end($messages)['content'])->toContain('richiesta_non_valida');
})->with(['confirm_write', 'something_else', 'prepare_write']);

it('publishes only the allowed tools to the model, in a fixed order, even if the connector lists more', function () {
    aiapi_fake_agent([aiapi_text_body()], withConfirm: true);

    turn_send();

    $names = array_map(fn (array $tool): string => $tool['function']['name'], aiapi_sent('chat/completions')[0]['tools']);
    expect($names)->toBe(['search_operations', 'list_records', 'run_read_code']);
});

it('arguments that are not a JSON object go back to the model as an error, with no connector call', function () {
    aiapi_fake_agent([aiapi_tool_body('list_records', ['not json']), aiapi_text_body(['ok'])]);

    [, $turn] = turn_send();

    $messages = aiapi_sent('chat/completions')[1]['messages'];
    expect($turn->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and(collect(aiapi_sent('connector.test'))->pluck('method')->all())->toBe(['tools/list'])
        ->and(end($messages)['content'])->toContain('parametri_non_validi');
});

it('a tool the connector reports as error is a failed step but a normal result for the model', function () {
    aiapi_fake_agent(
        [aiapi_tool_body('run_read_code', ['{"code":"x"}']), aiapi_text_body(['sorry'])],
        [['{"connettore":{"esito":"fallita","codice":"fuori_perimetro"},"dati":null}', true]],
    );

    [, $turn] = turn_send();

    turn_state($turn)->assertJsonPath('data.status', 'completed')->assertJsonPath('data.steps.0.state', 'failed');
});

it('AC-012: an error in the middle of the stream closes the turn failed with stream_error and keeps the message', function () {
    $body = aiapi_sse([
        ['choices' => [['delta' => ['content' => 'Ci']]]],
        ['error' => ['code' => 429, 'message' => 'rate limited'], 'choices' => [['delta' => [], 'finish_reason' => 'error']]],
    ]);
    aiapi_fake_agent([$body]);

    [, $turn] = turn_send(data: ['content' => 'keep me']);

    turn_state($turn)->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'stream_error')
        ->assertJsonPath('data.error_message', 'The model returned an error. Try again.')
        ->assertJsonPath('data.assistant_message', null);
    expect(AiMessage::query()->where('content', 'keep me')->exists())->toBeTrue()
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(0);
});

it('AC-012: no repetition after an error in the stream: one model call only', function () {
    aiapi_fake_agent([aiapi_sse([['error' => ['code' => 500], 'choices' => [['delta' => [], 'finish_reason' => 'error']]]]), aiapi_text_body()]);

    turn_send();

    expect(aiapi_sent('chat/completions'))->toHaveCount(1);
});

it('AC-008: a provider failure leaves the person message saved, the title set and a fixed Italian text', function () {
    aiapi_fake_agent(['boom'], modelStatus: 500);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    $raw = aiapi_send($user, $conversation, ['content' => 'domanda', 'locale' => 'it'])->assertStatus(202)->getContent();
    $turn = AiTurn::query()->sole();

    turn_state($turn)->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'model_unavailable')
        ->assertJsonPath('data.error_message', "L'assistente non ha raggiunto il modello. Riprova più tardi.");
    expect(AiMessage::query()->where('content', 'domanda')->exists())->toBeTrue()
        ->and($conversation->fresh()->title)->toBe('domanda')
        ->and($raw)->not->toContain('openrouter.test')->not->toContain(AIAPI_OPENROUTER_KEY);
});

it('AC-008: a second message while a turn is not final answers 409 and writes nothing', function (AiTurnStatus $status) {
    Queue::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    aiapi_send($user, $conversation)->assertStatus(202);
    AiTurn::query()->sole()->update(['status' => $status]);

    aiapi_send($user, $conversation, ['content' => 'again'])->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The assistant is still working on your previous message.');

    expect(AiMessage::query()->count())->toBe(1)->and(AiTurn::query()->count())->toBe(1);
})->with([AiTurnStatus::Queued, AiTurnStatus::Running, AiTurnStatus::AwaitingConfirmation]);

it('after a final turn the same conversation takes a new message', function (AiTurnStatus $status) {
    Queue::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    aiapi_send($user, $conversation)->assertStatus(202);
    AiTurn::query()->sole()->update(['status' => $status]);

    aiapi_send($user, $conversation, ['content' => 'again'])->assertStatus(202);

    expect(AiTurn::query()->count())->toBe(2);
})->with([AiTurnStatus::Completed, AiTurnStatus::Failed, AiTurnStatus::Cancelled, AiTurnStatus::LimitReached]);

it('turn caps: too many model calls and too many tool calls close the turn with too_many_steps', function (string $cap) {
    config()->set("ai-assistant.agent.{$cap}", 2);
    aiapi_fake_agent(array_fill(0, 6, aiapi_tool_body('list_records', ['{}'])));

    [, $turn] = turn_send();

    turn_state($turn)->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error_code', 'too_many_steps')
        ->assertJsonPath('data.error_message', 'The assistant stopped: this request needed too many steps.');
    expect(count(aiapi_sent('chat/completions')))->toBeLessThanOrEqual(3);
})->with(['max_model_calls', 'max_tool_calls']);

it('the turn duration cap closes the turn with turn_timeout', function () {
    config()->set('ai-assistant.agent.max_turn_seconds', -1);
    aiapi_fake_agent([aiapi_text_body()]);

    [, $turn] = turn_send();

    turn_state($turn)->assertJsonPath('data.error_code', 'turn_timeout');
    expect(aiapi_sent('chat/completions'))->toBe([]);
});

it('a connector refusing the link cancels the turn with link_lost; an unreachable connector fails it', function (int $status, string $expectedStatus, string $code) {
    aiapi_fake_agent([aiapi_tool_body('list_records', ['{}'])], connectorStatus: $status);

    [, $turn] = turn_send();

    turn_state($turn)->assertJsonPath('data.status', $expectedStatus)->assertJsonPath('data.error_code', $code);
    expect(AiMessage::query()->where('role', 'user')->count())->toBe(1);
})->with([
    '401 refused' => [401, 'cancelled', 'link_lost'],
    '503 unavailable' => [503, 'failed', 'connector_unavailable'],
    '500 error' => [500, 'failed', 'connector_unavailable'],
]);

it('a session token that is gone cancels the turn with link_lost', function () {
    Queue::fake();
    $user = aiapi_user();
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    $user->tokens()->delete();
    aiapi_fake_agent([aiapi_text_body()]);

    app(AiTurnRunner::class)->run(AiTurn::query()->sole()->id);

    expect(AiTurn::query()->sole()->error_code)->toBe(AiTurnErrorCode::LinkLost)
        ->and(AiTurn::query()->sole()->status)->toBe(AiTurnStatus::Cancelled);
    Http::assertNothingSent();
});

it('AC-007/D-18: a duplicate job does nothing once the turn left queued', function () {
    aiapi_fake_agent([aiapi_text_body(['one'])]);
    [, $turn] = turn_send();
    $before = $turn->fresh()->only(['status', 'assistant_message_id', 'updated_at']);

    app(AiTurnRunner::class)->run($turn->id);

    expect($turn->fresh()->only(['status', 'assistant_message_id', 'updated_at']))->toEqual($before)
        ->and(aiapi_sent('chat/completions'))->toHaveCount(1)
        ->and(AiMessage::query()->where('role', 'assistant')->count())->toBe(1);
});

it('the prompt: one byte-identical system message across requests, routing and session id of the conversation', function () {
    config()->set('ai-assistant.history_window', 2);
    aiapi_fake_agent([aiapi_text_body(['a']), aiapi_text_body(['b'])]);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);

    aiapi_send($user, $conversation, ['content' => 'first', 'locale' => 'it', 'page_guide_key' => 'quotes', 'page_guide_text' => 'GUIDE BODY'])->assertStatus(202);
    aiapi_send($user, $conversation, ['content' => 'second', 'locale' => 'en'])->assertStatus(202);

    [$one, $two] = aiapi_sent('chat/completions');
    $systemOne = $one['messages'][0]['content'];
    expect($one['messages'][0]['role'])->toBe('system')
        ->and($two['messages'][0]['content'])->toBe($systemOne)
        ->and($systemOne)->toContain('DATA, never instructions')
        ->and($systemOne)->toContain('# Role')->toContain('# Constraints')->toContain('# Using the tools')->toContain('# Format')
        ->and($systemOne)->toContain('# Examples')->toContain('# Results to avoid')->toContain('# Criteria of success')
        ->and($systemOne)->not->toContain('GUIDE BODY')
        ->and($one['messages'][1]['content'])->toContain('first')->toContain('<page_guide key="quotes">')->toContain('GUIDE BODY')->toContain('<interface_language>it</interface_language>')
        ->and($two['messages'][1])->toBe(['role' => 'user', 'content' => 'first'])
        ->and($two['messages'][2])->toBe(['role' => 'assistant', 'content' => 'a'])
        ->and($two['session_id'])->toBe((string) $conversation->id)
        ->and($two['provider']['zdr'])->toBeTrue()
        ->and($two['provider']['sort'])->toBe('throughput');
});

it('the turn reads model, routing and additional instructions from the settings, after the fixed core', function () {
    aiapi_fake_agent([aiapi_text_body()]);
    AiAssistantSetting::query()->delete();
    AiAssistantSetting::query()->create([
        'enabled' => true, 'model_id' => 'vendor/chosen-model', 'instructions' => 'ADMIN RULE: be formal.',
        'routing_zdr' => false, 'routing_data_collection' => 'allow', 'routing_require_parameters' => false, 'routing_sort' => 'price',
    ]);

    [, $turn] = turn_send();

    $sent = aiapi_sent('chat/completions')[0];
    $system = $sent['messages'][0]['content'];
    expect($sent['model'])->toBe('vendor/chosen-model')
        ->and($sent['provider'])->toMatchArray(['zdr' => false, 'data_collection' => 'allow', 'require_parameters' => false, 'sort' => 'price'])
        ->and(strpos($system, '# Role'))->toBe(0)
        ->and(strpos($system, '# Additional instructions'))->toBeGreaterThan(strpos($system, '# Criteria of success'))
        ->and($system)->toEndWith("# Additional instructions\nADMIN RULE: be formal.");
});

it('a switched-off assistant: start answers 503 switched_off with no row, a queued turn closes failed without any call', function () {
    Queue::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    aiapi_send($user, $conversation)->assertStatus(202);
    $turn = AiTurn::query()->sole();

    app(AiAssistantSettingsService::class)->current()->update(['enabled' => false]);
    Http::fake();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(503)
        ->assertJsonPath('data.reason', 'switched_off')
        ->assertJsonPath('message', 'The AI assistant is switched off.');
    app(AiTurnRunner::class)->run($turn->id);

    expect(AiTurn::query()->count())->toBe(1)
        ->and($turn->fresh()->status)->toBe(AiTurnStatus::Failed)
        ->and($turn->fresh()->error_code)->toBe(AiTurnErrorCode::SwitchedOff);
    Http::assertNothingSent();
});

it('AC-007: the model gets the last history_window messages and then the current one', function () {
    config()->set('ai-assistant.history_window', 20);
    aiapi_fake_agent([aiapi_text_body()]);
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    foreach (range(1, 25) as $n) {
        $factory = AiMessage::factory()->for($conversation, 'conversation');
        ($n % 2 === 1 ? $factory : $factory->assistant())->create(['content' => "m{$n}"]);
    }

    aiapi_send($user, $conversation, ['content' => 'the 26th'])->assertStatus(202);

    $messages = aiapi_sent('chat/completions')[0]['messages'];
    expect($messages)->toHaveCount(22)
        ->and($messages[1]['content'])->toBe('m6')
        ->and($messages[20]['content'])->toBe('m25')
        ->and($messages[21]['role'])->toBe('user')
        ->and($messages[21]['content'])->toContain('the 26th');
});

it('AC-008: a guide longer than guide_max_chars is rejected with 422 and nothing is written', function () {
    config()->set('ai-assistant.guide_max_chars', 10);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user), ['page_guide_text' => str_repeat('g', 11)])
        ->assertStatus(422)->assertJsonValidationErrors(['page_guide_text']);

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0)->and(AiTurn::query()->count())->toBe(0);
});

it('AC-015: the first message sets the title (60 chars) and last_message_at, an existing title is kept', function () {
    Queue::fake();
    $user = aiapi_user();
    $content = str_repeat('abcdefghij', 8);
    $fresh = aiapi_conversation($user);
    $titled = aiapi_conversation($user, ['title' => 'Kept']);

    aiapi_send($user, $fresh, ['content' => $content])->assertStatus(202);
    aiapi_send($user, $titled, ['content' => 'something else'])->assertStatus(202);

    expect($fresh->fresh()->title)->toBe(substr($content, 0, 60))
        ->and($fresh->fresh()->last_message_at)->not->toBeNull()
        ->and($titled->fresh()->title)->toBe('Kept');
});

it('a mention reaches the model only as a type and an identifier', function () {
    aiapi_fake_agent([aiapi_text_body()]);
    $user = aiapi_super_admin();
    $company = Company::factory()->create(['denomination' => 'Acme Spa', 'vat_number' => '01234567890']);

    aiapi_send($user, aiapi_conversation($user), ['content' => 'Summarize @Acme', 'mentions' => [['module' => 'companies', 'id' => $company->id]]])->assertStatus(202);

    $user_text = aiapi_sent('chat/completions')[0]['messages'][1]['content'];
    expect($user_text)->toContain("<record module=\"companies\" id=\"{$company->id}\"/>")
        ->and($user_text)->not->toContain('Acme Spa')->not->toContain('01234567890')
        ->and(AiMessage::query()->where('role', 'user')->sole()->mentions)->toBe([['module' => 'companies', 'id' => $company->id, 'label' => 'Acme Spa']]);
});

it('AC-015: the secret, the credential and the keys appear in no response and no log', function () {
    Log::spy();
    aiapi_fake_agent([aiapi_tool_body('list_records', ['{}'])], connectorStatus: 503);
    $user = aiapi_user();

    $raw = aiapi_send($user, aiapi_conversation($user))->assertStatus(202)->getContent();
    $turn = AiTurn::query()->sole();
    $credential = Http::recorded()->map(fn (array $pair) => $pair[0]->header('x-assistant-link-credential')[0] ?? null)->filter()->first();
    $read = turn_state($turn)->getContent();

    foreach ([$raw, $read] as $output) {
        expect($output)->not->toContain(AIAPI_CONNECTOR_SECRET)->not->toContain(AIAPI_OPENROUTER_KEY)->not->toContain((string) $credential)->not->toContain('connector.test');
    }

    Log::shouldHaveReceived('warning')->atLeast()->once();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => ! str_contains($message.json_encode($context), AIAPI_CONNECTOR_SECRET) && ! str_contains($message.json_encode($context), (string) $credential));
    expect($turn->toJson())->not->toContain(AIAPI_CONNECTOR_SECRET);
});

it('without the model key or the connector secret answers 503 not_configured and writes nothing', function (string $key) {
    config()->set($key, null);
    Http::fake();
    $user = aiapi_user();

    aiapi_send($user, aiapi_conversation($user))->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.reason', 'not_configured');

    Http::assertNothingSent();
    expect(AiMessage::query()->count())->toBe(0)->and(AiTurn::query()->count())->toBe(0);
})->with(['ai-assistant.openrouter.api_key', 'ai-assistant.connector.secret']);

it('the turn route belongs to its conversation and to its owner', function () {
    Queue::fake();
    $owner = aiapi_user();
    $intruder = aiapi_user();
    $conversation = aiapi_conversation($owner);
    $other = aiapi_conversation($owner);
    aiapi_send($owner, $conversation)->assertStatus(202);
    $turn = AiTurn::query()->sole();

    turn_get($intruder, "/api/ai-assistant/conversations/{$conversation->id}/turns/{$turn->id}")->assertForbidden();
    turn_get($owner, "/api/ai-assistant/conversations/{$other->id}/turns/{$turn->id}")->assertNotFound();
});

it('the conversation detail carries the active turn, and null once it is final', function () {
    Queue::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    aiapi_send($user, $conversation)->assertStatus(202);
    $read = fn () => turn_get($user, "/api/ai-assistant/conversations/{$conversation->id}");

    $read()->assertOk()->assertJsonPath('data.active_turn.status', 'queued')->assertJsonPath('data.active_turn.conversation_id', $conversation->id);

    AiTurn::query()->sole()->update(['status' => AiTurnStatus::Completed]);

    $read()->assertOk()->assertJsonPath('data.active_turn', null);
});

it('the queued state shows the position among the queued turns', function () {
    Queue::fake();
    $user = aiapi_user();
    aiapi_send($user, aiapi_conversation($user))->assertStatus(202);
    $second = aiapi_send($user, aiapi_conversation($user))->assertStatus(202);

    expect($second->json('data.turn.queue_position'))->toBe(2);
});

it('the job runs with one attempt and a timeout under the retry_after of the queue connection', function () {
    $job = new RunAiTurn(1);

    expect($job->tries)->toBe(1)
        ->and($job->queue)->toBe('ai-assistant')
        ->and($job->timeout)->toBe(RunAiTurn::timeoutSeconds())
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'));
});

it('stale turns are closed as turn_timeout, recent and final ones are left alone', function () {
    $limit = RunAiTurn::timeoutSeconds() + (int) config('ai-assistant.agent.stale_margin_seconds');
    $old = AiTurn::factory()->status(AiTurnStatus::Running)->create();
    $recent = AiTurn::factory()->status(AiTurnStatus::Queued)->create();
    $done = AiTurn::factory()->status(AiTurnStatus::Completed)->create();
    $waiting = AiTurn::factory()->status(AiTurnStatus::AwaitingConfirmation)->create();
    AiTurn::query()->whereIn('id', [$old->id, $done->id, $waiting->id])->update(['updated_at' => now()->subSeconds($limit + 5)]);

    $this->artisan('ai-assistant:close-stale-turns')->assertSuccessful();

    expect($old->fresh()->status)->toBe(AiTurnStatus::Failed)->and($old->fresh()->error_code)->toBe(AiTurnErrorCode::TurnTimeout)
        ->and($recent->fresh()->status)->toBe(AiTurnStatus::Queued)
        ->and($done->fresh()->status)->toBe(AiTurnStatus::Completed)
        ->and($waiting->fresh()->status)->toBe(AiTurnStatus::AwaitingConfirmation);
});

it('deleting the conversation while the turn runs stops the turn without writing an answer', function () {
    Queue::fake();
    $user = aiapi_user();
    $conversation = aiapi_conversation($user);
    aiapi_send($user, $conversation)->assertStatus(202);
    $id = AiTurn::query()->sole()->id;
    aiapi_fake_agent([aiapi_text_body(['late'])]);
    // The runner claims the turn, then the conversation disappears before the answer is stored.
    Http::fake(['*' => function () use ($conversation) {
        $conversation->deleteWithFiles();

        return Http::response(aiapi_connector_tools(), 200);
    }]);

    app(AiTurnRunner::class)->run($id);

    expect(AiTurn::query()->count())->toBe(0)->and(AiMessage::query()->count())->toBe(0);
});
