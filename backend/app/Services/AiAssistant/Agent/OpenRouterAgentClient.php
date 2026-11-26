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
     * `reasoning.max_tokens` of a call to a model that reasons: the configured value, always strictly
     * under `max_tokens` (null when no such value exists).
     */
    public static function reasoningMaxTokens(): ?int
    {
        $limit = min((int) config('ai-assistant.agent.reasoning_max_tokens'), self::maxTokens() - 1);

        return $limit >= 1 ? $limit : null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages  system message first
     * @param  list<array<string, mixed>>  $tools  OpenAI function definitions, in a fixed order
     * @param  (callable(string): void)|null  $onText  receives each text fragment as it streams
     * @param  bool  $limitReasoning  the model declares `reasoning`: its tokens are limited under `max_tokens`
     *
     * @throws AgentModelException
     */
    public function complete(array $messages, array $tools, int $conversationId, AiEffectiveSettings $settings, AiPriceCeiling $prices, ?callable $onText = null, bool $limitReasoning = false): AgentModelResult
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->withOptions(['stream' => true])
                ->withoutRedirecting()
                ->timeout((int) config('ai-assistant.timeout_seconds'))
                ->connectTimeout((int) config('ai-assistant.connect_timeout_seconds'))
                ->acceptJson()
                ->post($this->url(), $this->payload($messages, $tools, $conversationId, $settings, $prices, $limitReasoning));

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
        $stream = new AgentStream;

        try {
            foreach ($events as $event) {
                $stream->started = true;

                if ($event['data'] === '[DONE]') {
                    break;
                }

                $chunk = $this->decode($event['data']);
                $this->assertNoErrorChunk($chunk, $stream);
                $stream->absorb($chunk, $onText);
            }

            $this->assertFinished($stream);
        } catch (AgentModelException $exception) {
            // The reason was already logged; what matters here is whether the provider may have charged the call.
            throw new AgentModelException($exception->errorCode, $stream->started, $stream->usage ?? []);
        } catch (Throwable $exception) {
            // Mid-stream read timeouts surface as plain runtime exceptions of the HTTP stream.
            $this->fail(AiTurnErrorCode::ModelUnavailable, 'transport', ['exception' => $exception::class], $stream->started, $stream->usage ?? []);
        }

        return $stream->result();
    }

    /**
     * OpenRouter can fail in the middle of a 200 stream: the usage of the chunk still counts for the cost.
     *
     * @param  array<string, mixed>  $chunk
     */
    private function assertNoErrorChunk(array $chunk, AgentStream $stream): void
    {
        if (isset($chunk['error'])) {
            $stream->usage = is_array($chunk['usage'] ?? null) ? $chunk['usage'] : $stream->usage;
            $this->fail(AiTurnErrorCode::StreamError, 'stream_error_chunk', ['code' => $chunk['error']['code'] ?? null]);
        }
    }

    private function assertFinished(AgentStream $stream): void
    {
        if ($stream->finish === null || $stream->finish === 'error') {
            $this->fail(AiTurnErrorCode::StreamError, $stream->finish === null ? 'truncated_stream' : 'finish_reason_error');
        }
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
    private function payload(array $messages, array $tools, int $conversationId, AiEffectiveSettings $settings, AiPriceCeiling $prices, bool $limitReasoning): array
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

        // Only for a model that declares `reasoning`: with `require_parameters` the key would exclude providers otherwise.
        if ($limitReasoning && self::reasoningMaxTokens() !== null) {
            $payload['reasoning'] = ['max_tokens' => self::reasoningMaxTokens()];
        }

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
