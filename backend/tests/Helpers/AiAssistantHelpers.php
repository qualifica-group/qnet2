<?php

use App\Models\AiConversation;
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
const AIAPI_CONNECTOR_URL = 'http://connector.test';
const AIAPI_CONNECTOR_SECRET = 'fake-assistant-secret-0123456789abcdef';
const AIAPI_OPENROUTER_KEY = 'sk-or-fake-key';

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
     *
     * @param  list<string>  $modelBodies
     * @param  list<string>  $toolResults
     */
    function aiapi_fake_agent(array $modelBodies, array $toolResults = [], int $modelStatus = 200, int $connectorStatus = 200, bool $withConfirm = false): void
    {
        $model = Http::sequence();
        foreach ($modelBodies as $body) {
            $model->push($body, $modelStatus, ['Content-Type' => 'text/event-stream']);
        }

        $calls = 0;

        Http::fake([
            'openrouter.test/*' => $model,
            'connector.test/*' => function (Request $request) use ($toolResults, $connectorStatus, $withConfirm, &$calls) {
                if ($connectorStatus !== 200) {
                    return Http::response('', $connectorStatus);
                }

                $method = $request->data()['method'] ?? '';
                $body = $method === 'tools/list'
                    ? aiapi_connector_tools($withConfirm)
                    : aiapi_connector_result($toolResults[$calls++] ?? '{"connettore":{"esito":"riuscita"},"dati":[]}');

                return Http::response($body, 200, ['Content-Type' => 'text/event-stream']);
            },
        ]);
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
