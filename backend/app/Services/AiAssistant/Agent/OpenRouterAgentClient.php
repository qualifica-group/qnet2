<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Costs\AiPriceCeiling;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Streaming chat completion with tool calls on OpenRouter (spec 0433, D-13). Model and
 * routing are the effective settings the caller passes, the key comes from
 * `ai-assistant.openrouter`. HTTP status is not enough on OpenRouter: an error can arrive
 * in the middle of a 200 stream, so every chunk is checked for `error` and `finish_reason`.
 */
final class OpenRouterAgentClient
{
    public static function isConfigured(): bool
    {
        return filled(config('ai-assistant.openrouter.api_key'));
    }

    /**
     * `max_tokens` of every call: the same number the cost reservation is priced with (reasoning tokens count under it).
     */
    public static function maxTokens(): int
    {
        return (int) config('ai-assistant.agent.max_output_tokens');
    }

    /**
     * @param  list<array<string, mixed>>  $messages  system message first
     * @param  list<array<string, mixed>>  $tools  OpenAI function definitions, in a fixed order
     * @param  (callable(string): void)|null  $onText  receives each text fragment as it streams
     *
     * @throws AgentModelException
     */
    public function complete(array $messages, array $tools, int $conversationId, AiEffectiveSettings $settings, AiPriceCeiling $prices, ?callable $onText = null): AgentModelResult
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->withOptions(['stream' => true])
                ->withoutRedirecting()
                ->timeout((int) config('ai-assistant.timeout_seconds'))
                ->connectTimeout((int) config('ai-assistant.connect_timeout_seconds'))
                ->acceptJson()
                ->post($this->url(), $this->payload($messages, $tools, $conversationId, $settings, $prices));

            if (! $response->successful()) {
                $this->fail(AiTurnErrorCode::ModelUnavailable, 'http_status', ['status' => $response->status()]);
            }

            return $this->assemble(SseLineReader::events($response->toPsrResponse()->getBody()), $onText);
        } catch (AgentModelException $exception) {
            throw $exception;
        } catch (ConnectionException) {
            $this->fail(AiTurnErrorCode::ModelUnavailable, 'connection');
        } catch (Throwable $exception) {
            // Mid-stream read timeouts surface as plain runtime exceptions of the HTTP stream.
            $this->fail(AiTurnErrorCode::ModelUnavailable, 'transport', ['exception' => $exception::class]);
        }
    }

    /**
     * @param  iterable<array{event: ?string, data: string}>  $events
     */
    private function assemble(iterable $events, ?callable $onText): AgentModelResult
    {
        $text = '';
        $reasoning = '';
        $details = [];
        $calls = [];
        $finish = null;
        $usage = null;
        $model = null;
        $provider = null;

        $started = false;

        try {
            foreach ($events as $event) {
                $started = true;

                if ($event['data'] === '[DONE]') {
                    break;
                }

                $chunk = $this->decode($event['data']);
                $model = $chunk['model'] ?? $model;
                $provider = $chunk['provider'] ?? $provider;

                if (is_array($chunk['usage'] ?? null)) {
                    $usage = $chunk['usage'];
                }

                if (isset($chunk['error'])) {
                    $this->fail(AiTurnErrorCode::StreamError, 'stream_error_chunk', ['code' => $chunk['error']['code'] ?? null]);
                }

                $choice = $chunk['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];

                if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
                    $text .= $delta['content'];
                    if ($onText !== null) {
                        $onText($delta['content']);
                    }
                }

                if (is_string($delta['reasoning'] ?? null)) {
                    $reasoning .= $delta['reasoning'];
                }

                // Each object is kept exactly as it arrived and in order: OpenRouter wants the sequence back unchanged.
                foreach ($delta['reasoning_details'] ?? [] as $detail) {
                    if (is_array($detail)) {
                        $details[] = $detail;
                    }
                }

                foreach ($delta['tool_calls'] ?? [] as $fragment) {
                    $this->mergeToolCall($calls, $fragment);
                }

                $finish = $choice['finish_reason'] ?? $finish;
            }

            if ($finish === null || $finish === 'error') {
                $this->fail(AiTurnErrorCode::StreamError, $finish === null ? 'truncated_stream' : 'finish_reason_error');
            }
        } catch (AgentModelException $exception) {
            // The reason was already logged; what matters here is whether the provider may have charged the call.
            throw new AgentModelException($exception->errorCode, $started, $usage ?? []);
        } catch (Throwable $exception) {
            // Mid-stream read timeouts surface as plain runtime exceptions of the HTTP stream.
            $this->fail(AiTurnErrorCode::ModelUnavailable, 'transport', ['exception' => $exception::class], $started, $usage ?? []);
        }

        ksort($calls);

        return new AgentModelResult(
            text: $text,
            toolCalls: array_values($calls),
            reasoningDetails: $details,
            reasoning: $details === [] && $reasoning !== '' ? $reasoning : null,
            finishReason: (string) $finish,
            rawUsage: $usage ?? [],
            model: is_string($model) ? $model : null,
            provider: is_string($provider) ? $provider : null,
        );
    }

    /**
     * Fragments of one call share an `index`: id and name arrive first (the first non-empty one
     * wins), `arguments` is a JSON string cut in pieces and only concatenated.
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $calls
     * @param  array<string, mixed>  $fragment
     */
    private function mergeToolCall(array &$calls, array $fragment): void
    {
        $index = (int) ($fragment['index'] ?? 0);
        $call = $calls[$index] ?? ['id' => '', 'name' => '', 'arguments' => ''];

        foreach (['id' => $fragment['id'] ?? null, 'name' => $fragment['function']['name'] ?? null] as $key => $value) {
            if ($call[$key] === '' && is_string($value)) {
                $call[$key] = $value;
            }
        }

        if (is_string($fragment['function']['arguments'] ?? null)) {
            $call['arguments'] .= $fragment['function']['arguments'];
        }

        $calls[$index] = $call;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $data): array
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail(AiTurnErrorCode::StreamError, 'malformed_json');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function url(): string
    {
        return rtrim((string) config('ai-assistant.openrouter.base_url'), '/').'/chat/completions';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.config('ai-assistant.openrouter.api_key'),
            'HTTP-Referer' => (string) config('ai-assistant.openrouter.site_url'),
            'X-Title' => (string) config('ai-assistant.openrouter.app_name'),
        ], 'filled');
    }

    /**
     * Tools only when there are some: an empty `tools` array is rejected by some providers.
     * One tool call per round: the connector runs one execution per user at a time.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function payload(array $messages, array $tools, int $conversationId, AiEffectiveSettings $settings, AiPriceCeiling $prices): array
    {
        $payload = [
            'model' => $settings->modelId,
            'stream' => true,
            'max_tokens' => self::maxTokens(),
            'messages' => $messages,
            'session_id' => (string) $conversationId,
            // The price ceiling that priced the reservation: a dearer endpoint is never used.
            'provider' => $settings->routing + ['max_price' => $prices->maxPrice()],
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['parallel_tool_calls'] = false;
        }

        return $payload;
    }

    /**
     * Logs a sanitized reason (never keys, URLs, bodies or user content) and throws.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $rawUsage
     *
     * @throws AgentModelException
     */
    private function fail(AiTurnErrorCode $code, string $reason, array $context = [], bool $responseStarted = false, array $rawUsage = []): never
    {
        Log::warning('AI agent model call failed', ['reason' => $reason] + $context);

        throw new AgentModelException($code, $responseStarted, $rawUsage);
    }
}
