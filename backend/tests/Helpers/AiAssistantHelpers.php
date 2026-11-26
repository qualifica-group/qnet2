<?php

use App\Models\AiConversation;
use App\Models\AiTurn;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| AI assistant API helpers (spec 0347, 0433)
|--------------------------------------------------------------------------
| Shared by tests/Feature/AiAssistant/*: parallel runs load only their own
| test files, so every helper used by more than one file lives here.
| OpenRouter and the connector are always faked with Http::fake.
*/

const AIAPI_OPENROUTER_URL = 'https://openrouter.test/api/v1';
// REQUIREMENT CHANGED (security review): the client refuses http to a host that is not loopback, so the fake connector is https.
const AIAPI_CONNECTOR_URL = 'https://connector.test';
const AIAPI_CONNECTOR_SECRET = 'fake-assistant-secret-0123456789abcdef';
const AIAPI_OPENROUTER_KEY = 'sk-or-fake-key';
const AIAPI_PREVIEW_ID = 'a81f00112233445566778899';
const AIAPI_MODEL_IDS = ['deepseek/deepseek-v4-flash-0731', 'vendor/chosen-model'];

if (! function_exists('aiapi_user')) {
    /**
     * A user holding `ai-assistant.use` plus the given extra permissions.
     *
     * @param  list<string>  $permissions
     */
    function aiapi_user(array $permissions = []): User
    {
        $user = User::factory()->create();

        foreach (['ai-assistant.use', ...$permissions] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('aiapi_super_admin')) {
    function aiapi_super_admin(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        return tap(User::factory()->create())->assignRole('super-admin');
    }
}

if (! function_exists('aiapi_configure')) {
    /**
     * Surfaces on, OpenRouter and connector configured with fake values, and the API client where
     * the links of the assistant belong (the real AssistantLinkService opens them).
     */
    function aiapi_configure(bool $openLinks = true): void
    {
        // REQUIREMENT CHANGED (D-16): both surfaces are off by default and the API answers 404 without them.
        config()->set('ai-assistant.floating_enabled', true);
        config()->set('ai-assistant.page_enabled', true);
        // REQUIREMENT CHANGED (spec 0433): the model is text-only by default; the attachment tests need one that takes files.
        config()->set('ai-assistant.model_accepts_attachments', true);
        config()->set('ai-assistant.openrouter', ['base_url' => AIAPI_OPENROUTER_URL, 'api_key' => AIAPI_OPENROUTER_KEY, 'site_url' => null, 'app_name' => null]);
        config()->set('ai-assistant.connector.url', AIAPI_CONNECTOR_URL);
        config()->set('ai-assistant.connector.host', 'connector.public.test');
        config()->set('ai-assistant.connector.secret', AIAPI_CONNECTOR_SECRET);

        if ($openLinks) {
            [$client] = createApiClientWithKey();
            config()->set('mcp-link.assistant_client_id', $client->id);
            config()->set('mcp-link.release_commit', 'abc1234');
        }
    }
}

if (! function_exists('aiapi_sse')) {
    /**
     * @param  list<array<string, mixed>>  $chunks
     */
    function aiapi_sse(array $chunks): string
    {
        return implode('', array_map(fn (array $chunk): string => 'data: '.json_encode($chunk)."\n\n", $chunks))."data: [DONE]\n\n";
    }
}

if (! function_exists('aiapi_text_body')) {
    /**
     * A streamed text answer of the model, closed by the usage chunk with the cost.
     *
     * @param  list<string>  $fragments
     */
    function aiapi_text_body(array $fragments = ['Ci', 'ao', '!'], array $usage = []): string
    {
        $chunks = array_map(fn (string $text): array => ['model' => 'model-x', 'choices' => [['delta' => ['content' => $text]]]], $fragments);
        $chunks[] = ['model' => 'model-x', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]];
        $chunks[] = ['model' => 'model-x', 'choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => $usage + [
            'prompt_tokens' => 100, 'completion_tokens' => 20, 'cost' => 0.0012,
            'prompt_tokens_details' => ['cached_tokens' => 40], 'completion_tokens_details' => ['reasoning_tokens' => 5],
        ]];

        return aiapi_sse($chunks);
    }
}

if (! function_exists('aiapi_tool_body')) {
    /**
     * A streamed round that ends with one tool call, its arguments cut in the given pieces.
     *
     * @param  list<string>  $argumentPieces
     * @param  list<array<string, mixed>>  $reasoningDetails
     */
    function aiapi_tool_body(string $name, array $argumentPieces, string $id = 'call_1', array $reasoningDetails = []): string
    {
        $chunks = [['model' => 'model-x', 'choices' => [['delta' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['index' => 0, 'id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => '']],
        ]]]]]];

        if ($reasoningDetails !== []) {
            $chunks[] = ['model' => 'model-x', 'choices' => [['delta' => ['reasoning_details' => $reasoningDetails]]]];
        }

        foreach ($argumentPieces as $piece) {
            $chunks[] = ['model' => 'model-x', 'choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => $piece]]]]]]];
        }

        $chunks[] = ['model' => 'model-x', 'choices' => [['delta' => [], 'finish_reason' => 'tool_calls']], 'usage' => [
            'prompt_tokens' => 50, 'completion_tokens' => 10, 'cost' => 0.0005,
        ]];

        return aiapi_sse($chunks);
    }
}

if (! function_exists('aiapi_connector_event')) {
    /**
     * The single `data:` event of a connector answer.
     *
     * @param  array<string, mixed>  $message  JSON-RPC message
     */
    function aiapi_connector_event(array $message): string
    {
        return "event: message\ndata: ".json_encode($message + ['jsonrpc' => '2.0', 'id' => 1])."\n\n";
    }
}

if (! function_exists('aiapi_connector_tools')) {
    /**
     * The `tools/list` answer: the four assistant tools, plus `confirm_write` the connector must never publish.
     */
    function aiapi_connector_tools(bool $withConfirm = false): string
    {
        $tools = array_map(fn (string $name): array => [
            'name' => $name, 'title' => $name, 'description' => "Description of {$name}",
            'inputSchema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string']], 'additionalProperties' => false],
        ], ['prepare_write', 'run_read_code', 'list_records', 'search_operations', ...($withConfirm ? ['confirm_write'] : [])]);

        return aiapi_connector_event(['result' => ['tools' => $tools]]);
    }
}

if (! function_exists('aiapi_connector_result')) {
    function aiapi_connector_result(string $text, bool $isError = false): string
    {
        return aiapi_connector_event(['result' => ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $isError]]);
    }
}

if (! function_exists('aiapi_fake_agent')) {
    /**
     * Fakes OpenRouter (one body per model round, in order) and the connector (tools/list and the
     * body of each tools/call, in order). A connector status other than 200 fails every call.
     * `POST /assistant/confirm` answers the bodies of `$confirmResults` in order, with `$confirmStatus`.
     *
     * @param  list<string>  $modelBodies
     * @param  list<string>  $toolResults
     * @param  list<string>  $confirmResults
     */
    function aiapi_fake_agent(array $modelBodies, array $toolResults = [], int $modelStatus = 200, int $connectorStatus = 200, bool $withConfirm = false, ?int $contextLength = 200000, array $confirmResults = [], int $confirmStatus = 200): void
    {
        $model = Http::sequence();
        foreach ($modelBodies as $body) {
            $model->push($body, $modelStatus, ['Content-Type' => 'text/event-stream']);
        }

        $calls = 0;
        $confirms = 0;

        // The price ceiling the reservation is priced with: one endpoint with tools, $1 / $2 per million tokens.
        $endpoint = ['tag' => 'fast', 'supported_parameters' => ['tools'], 'pricing' => ['prompt' => '0.000001', 'completion' => '0.000002', 'request' => '0']];

        Http::fake([
            '*/endpoints/zdr' => Http::response(['data' => array_map(fn (string $id): array => ['model_id' => $id, 'tag' => 'fast', 'safety_retention' => false], AIAPI_MODEL_IDS)]),
            '*/models/*/endpoints' => Http::response(['data' => ['endpoints' => [$endpoint]]]),
            // The model list the memory reads the context window from.
            'openrouter.test/api/v1/models*' => Http::response(['data' => $contextLength === null ? [] : array_map(fn (string $id): array => [
                'id' => $id, 'name' => $id, 'context_length' => $contextLength,
                'supported_parameters' => ['tools'],
                'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
                'pricing' => ['prompt' => '0.000001', 'completion' => '0.000002'],
            ], AIAPI_MODEL_IDS)]),
            'openrouter.test/api/v1/chat/completions' => $model,
            'connector.test/*' => function (Request $request) use ($toolResults, $connectorStatus, $withConfirm, $confirmResults, $confirmStatus, &$calls, &$confirms) {
                if (str_ends_with($request->url(), '/assistant/confirm')) {
                    return Http::response($confirmResults[$confirms++] ?? '{"connettore":{"esito":"riuscita"},"dati":{"testo":"done","codice_http":200,"risposta":{}}}', $confirmStatus, ['Content-Type' => 'application/json']);
                }

                if ($connectorStatus !== 200) {
                    return Http::response('', $connectorStatus);
                }

                $method = $request->data()['method'] ?? '';
                if ($method === 'tools/list') {
                    return Http::response(aiapi_connector_tools($withConfirm), 200, ['Content-Type' => 'text/event-stream']);
                }

                // A result is the text, or [text, isError].
                $result = (array) ($toolResults[$calls++] ?? '{"connettore":{"esito":"riuscita"},"dati":[]}');
                $body = aiapi_connector_result($result[0], $result[1] ?? false);

                return Http::response($body, 200, ['Content-Type' => 'text/event-stream']);
            },
        ]);
    }
}

if (! function_exists('aiapi_preview_result')) {
    /**
     * The `prepare_write` result of the connector: the envelope with the three fields of `dati`.
     */
    function aiapi_preview_result(string $id = 'a81f00112233445566778899', string $content = "### Anteprima\n\n**Nuova ora**: crea un'ora.", bool $confirmable = true): string
    {
        return json_encode(['connettore' => ['esito' => 'riuscita'], 'dati' => ['preview_id' => $id, 'confermabile' => $confirmable, 'anteprima' => $content]]);
    }
}

if (! function_exists('aiapi_waiting_turn')) {
    /**
     * Sends a message whose first model round prepares a write and runs the turn up to the wait
     * for the confirmation. The model rounds after the first are the ones of `$modelRounds` (default:
     * a text answer), the confirmation answers are `$confirmResults`. The expiry job must be faked by the caller.
     *
     * @param  list<string>  $modelRounds  bodies AFTER the first round
     * @param  list<string>  $confirmResults
     * @return array{0: AiTurn, 1: User, 2: AiConversation}
     */
    function aiapi_waiting_turn(?User $user = null, string $previewResult = '', array $modelRounds = [], array $confirmResults = [], int $confirmStatus = 200): array
    {
        $user ??= aiapi_user();
        aiapi_fake_agent(
            [aiapi_tool_body('prepare_write', ['{"operation_id":"POST /api/time-entries","body":{}}']), ...($modelRounds ?: [aiapi_text_body(['Done.'])])],
            [$previewResult ?: aiapi_preview_result(AIAPI_PREVIEW_ID)],
            confirmResults: $confirmResults,
            confirmStatus: $confirmStatus,
        );

        aiapi_send($user, $conversation = aiapi_conversation($user))->assertStatus(202);

        return [AiTurn::query()->latest('id')->firstOrFail(), $user, $conversation];
    }
}

if (! function_exists('aiapi_turn_url')) {
    function aiapi_turn_url(AiTurn $turn, string $suffix = ''): string
    {
        return "/api/ai-assistant/conversations/{$turn->ai_conversation_id}/turns/{$turn->id}{$suffix}";
    }
}

if (! function_exists('aiapi_turn_state')) {
    /**
     * GET of a turn as a user with a fresh token and no guard left over from a previous request.
     */
    function aiapi_turn_state(AiTurn $turn, ?User $user = null): TestResponse
    {
        app('auth')->forgetGuards();
        $user ??= $turn->conversation->user;

        return test()->withHeader('Authorization', 'Bearer '.$user->createToken('read')->plainTextToken)->getJson(aiapi_turn_url($turn));
    }
}

if (! function_exists('aiapi_decide')) {
    /**
     * POST of `confirm` or `cancel` on a turn, as the given user (default: the owner) with a fresh token.
     *
     * @param  array<string, mixed>|null  $body  default: the id of the open preview of the turn
     */
    function aiapi_decide(AiTurn $turn, string $action, ?User $user = null, ?array $body = null, ?string $url = null): TestResponse
    {
        app('auth')->forgetGuards();
        $user ??= $turn->conversation->user;
        $body ??= ['preview_id' => $turn->fresh()->preview['id'] ?? 'none'];

        return test()->withHeader('Authorization', 'Bearer '.$user->createToken('decide')->plainTextToken)
            ->postJson($url ?? aiapi_turn_url($turn, "/{$action}"), $body);
    }
}

if (! function_exists('aiapi_sent')) {
    /**
     * JSON bodies of the requests sent to a host, in order.
     *
     * @return list<array<string, mixed>>
     */
    function aiapi_sent(string $host): array
    {
        return Http::recorded()
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), $host))
            ->map(fn (array $pair): array => $pair[0]->data())
            ->values()
            ->all();
    }
}

if (! function_exists('aiapi_conversation')) {
    function aiapi_conversation(User $user, array $attributes = []): AiConversation
    {
        return AiConversation::factory()->create(['user_id' => $user->id, 'title' => '', 'last_message_at' => null, ...$attributes]);
    }
}

if (! function_exists('aiapi_send')) {
    /**
     * Posts a turn as multipart with a real Sanctum token (the turn opens the assistant link from it);
     * returns the test response. With the sync queue of the tests the turn has already run when it answers.
     *
     * @param  array<string, mixed>  $data
     */
    function aiapi_send(User $user, AiConversation $conversation, array $data = []): TestResponse
    {
        // A guard keeps the user of the previous request: forget it before changing token.
        app('auth')->forgetGuards();

        return test()->withHeader('Authorization', 'Bearer '.$user->createToken('web')->plainTextToken)->post(
            "/api/ai-assistant/conversations/{$conversation->id}/messages",
            ['content' => 'How do I create a quote?', 'locale' => 'en', ...$data],
            ['Accept' => 'application/json'],
        );
    }
}

if (! function_exists('aiapi_pdf')) {
    function aiapi_pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
    }
}

if (! function_exists('aiapi_png')) {
    function aiapi_png(string $name = 'pic.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}

if (! function_exists('aiapi_real_upload')) {
    /**
     * Upload backed by a real temp file: its MIME type is sniffed from the
     * content (the fake File of the framework derives it from the name only),
     * so it can model a spoofed extension.
     */
    function aiapi_real_upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'aiapi');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
