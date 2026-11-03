<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use App\Services\AiAssistant\Prompt\AiChatPrompt;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Shared transport for streaming drivers: request, status check, SSE decode
 * and error sanitizing. Subclasses only describe the provider wire format.
 */
abstract class AbstractSseChatDriver implements AiChatDriver
{
    abstract protected function driverName(): string;

    abstract protected function url(): string;

    /** @return array<string, string> */
    abstract protected function headers(): array;

    /** @return array<string, mixed> */
    abstract protected function payload(AiChatPrompt $prompt): array;

    /**
     * Wrap a decoded-event generator into AiStreamEvents.
     *
     * @param  Generator<int, array{event: ?string, data: array<string, mixed>|string}>  $events
     * @return Generator<int, AiStreamEvent>
     */
    abstract protected function translate(Generator $events): Generator;

    final public function stream(AiChatPrompt $prompt): Generator
    {
        try {
            $response = $this->request()->post($this->url(), $this->payload($prompt));

            if (! $response->successful()) {
                $this->fail('http_status', ['status' => $response->status()]);
            }

            yield from $this->translate($this->decoded(SseLineReader::events($response->toPsrResponse()->getBody())));
        } catch (AiProviderException $e) {
            throw $e;
        } catch (ConnectionException) {
            $this->fail('connection');
        } catch (Throwable $e) {
            // Mid-stream read timeouts and transport errors surface as plain
            // runtime exceptions from Guzzle's stream.
            $this->fail('transport', ['exception' => $e::class]);
        }
    }

    protected function request(): PendingRequest
    {
        return Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout((int) config('ai-assistant.timeout_seconds'))
            ->connectTimeout((int) config('ai-assistant.connect_timeout_seconds'))
            ->acceptJson();
    }

    /**
     * @param  Generator<int, array{event: ?string, data: string}>  $raw
     * @return Generator<int, array{event: ?string, data: array<string, mixed>|string}>
     */
    private function decoded(Generator $raw): Generator
    {
        foreach ($raw as $event) {
            if ($event['data'] === '[DONE]') {
                yield ['event' => $event['event'], 'data' => '[DONE]'];

                continue;
            }

            try {
                $data = json_decode($event['data'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->fail('malformed_json');
            }

            if (is_array($data)) {
                yield ['event' => $event['event'], 'data' => $data];
            }
        }
    }

    /**
     * Logs a sanitized reason (never keys, URLs, bodies or user content) and
     * throws the generic exception.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws AiProviderException
     */
    protected function fail(string $reason, array $context = []): never
    {
        Log::warning('AI provider request failed', ['driver' => $this->driverName(), 'reason' => $reason] + $context);

        throw new AiProviderException;
    }
}
