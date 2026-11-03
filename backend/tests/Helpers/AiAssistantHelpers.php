<?php

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| AI assistant API helpers (spec 0347)
|--------------------------------------------------------------------------
| Shared by tests/Feature/AiAssistant/Api/*: parallel runs load only their own
| test files, so every helper used by more than one file lives here.
*/

const AIAPI_PROVIDER_URL = 'https://llm.test/v1';

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
    function aiapi_configure(string $driver = 'openai'): void
    {
        // REQUIREMENT CHANGED (D-16): both surfaces are off by default and the API answers 404 without them.
        config()->set('ai-assistant.floating_enabled', true);
        config()->set('ai-assistant.page_enabled', true);
        config()->set('ai-assistant.driver', $driver);
        config()->set('ai-assistant.openai', ['base_url' => AIAPI_PROVIDER_URL, 'api_key' => 'sk-secret', 'model' => 'gpt-x']);
        config()->set('ai-assistant.anthropic', ['base_url' => 'https://claude.test/v1', 'api_key' => 'ak-secret', 'model' => 'claude-x', 'version' => '2023-06-01']);
    }
}

if (! function_exists('aiapi_openai_body')) {
    /**
     * @param  list<string>  $chunks
     */
    function aiapi_openai_body(array $chunks = ['Ci', 'ao', '!']): string
    {
        $body = '';
        foreach ($chunks as $chunk) {
            $body .= 'data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => ['content' => $chunk]]]])."\n\n";
        }

        return $body
            .'data: '.json_encode(['model' => 'gpt-x', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]])."\n\n"
            .'data: '.json_encode(['model' => 'gpt-x', 'choices' => [], 'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 3]])."\n\n"
            ."data: [DONE]\n\n";
    }
}

if (! function_exists('aiapi_anthropic_body')) {
    function aiapi_anthropic_body(): string
    {
        $events = [
            ['message_start', ['type' => 'message_start', 'message' => ['model' => 'claude-x', 'usage' => ['input_tokens' => 21, 'output_tokens' => 1]]]],
            ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'Ci']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => 'ao']]],
            ['content_block_delta', ['type' => 'content_block_delta', 'delta' => ['type' => 'text_delta', 'text' => '!']]],
            ['message_delta', ['type' => 'message_delta', 'usage' => ['output_tokens' => 7]]],
            ['message_stop', ['type' => 'message_stop']],
        ];

        return implode('', array_map(fn (array $e): string => "event: {$e[0]}\ndata: ".json_encode($e[1])."\n\n", $events));
    }
}

if (! function_exists('aiapi_fake_provider')) {
    function aiapi_fake_provider(?string $body = null, int $status = 200): void
    {
        Http::fake(['*' => Http::response($body ?? aiapi_openai_body(), $status, ['Content-Type' => 'text/event-stream'])]);
    }
}

if (! function_exists('aiapi_parse_sse')) {
    /**
     * @return list<array{event: string, data: array<string, mixed>}>
     */
    function aiapi_parse_sse(string $stream): array
    {
        $events = [];

        foreach (preg_split("/\n\n/", trim($stream)) as $block) {
            if (! preg_match('/^event: (.+)\ndata: (.+)$/s', $block, $match)) {
                continue;
            }

            $events[] = ['event' => $match[1], 'data' => json_decode($match[2], true)];
        }

        return $events;
    }
}

if (! function_exists('aiapi_sent_payload')) {
    /**
     * JSON body of the single request the faked provider received.
     *
     * @return array<string, mixed>
     */
    function aiapi_sent_payload(): array
    {
        $payload = [];
        Http::assertSent(function (Request $request) use (&$payload): bool {
            $payload = $request->data();

            return true;
        });

        return $payload;
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
     * Posts a turn as multipart; returns the test response.
     *
     * @param  array<string, mixed>  $data
     */
    function aiapi_send(User $user, AiConversation $conversation, array $data = []): TestResponse
    {
        Sanctum::actingAs($user);

        return test()->post(
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
