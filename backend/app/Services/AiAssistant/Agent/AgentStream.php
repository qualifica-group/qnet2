<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

/**
 * What one streamed model answer has said so far (spec 0433, D-13): text, reasoning, tool call
 * fragments, usage and the finish reason, each kept exactly as it arrived. It only accumulates;
 * deciding that a chunk is an error is the job of the client.
 */
final class AgentStream
{
    public string $text = '';

    public ?string $finish = null;

    /** @var array<string, mixed>|null */
    public ?array $usage = null;

    /** True once the first event arrived: from then on the provider may have charged the call. */
    public bool $started = false;

    private string $reasoning = '';

    /** @var list<array<string, mixed>> */
    private array $details = [];

    /** @var array<int, array{id: string, name: string, arguments: string}> */
    private array $calls = [];

    private ?string $model = null;

    private ?string $provider = null;

    /**
     * @param  array<string, mixed>  $chunk  a decoded chunk without `error`
     * @param  (callable(string): void)|null  $onText
     */
    public function absorb(array $chunk, ?callable $onText): void
    {
        $this->model = $chunk['model'] ?? $this->model;
        $this->provider = $chunk['provider'] ?? $this->provider;

        if (is_array($chunk['usage'] ?? null)) {
            $this->usage = $chunk['usage'];
        }

        $choice = $chunk['choices'][0] ?? [];
        $delta = $choice['delta'] ?? [];

        $this->absorbDelta($delta, $onText);
        $this->finish = $choice['finish_reason'] ?? $this->finish;
    }

    public function result(): AgentModelResult
    {
        ksort($this->calls);

        return new AgentModelResult(
            text: $this->text,
            toolCalls: array_values($this->calls),
            reasoningDetails: $this->details,
            reasoning: $this->details === [] && $this->reasoning !== '' ? $this->reasoning : null,
            finishReason: (string) $this->finish,
            rawUsage: $this->usage ?? [],
            model: is_string($this->model) ? $this->model : null,
            provider: is_string($this->provider) ? $this->provider : null,
        );
    }

    /**
     * @param  array<string, mixed>  $delta
     * @param  (callable(string): void)|null  $onText
     */
    private function absorbDelta(array $delta, ?callable $onText): void
    {
        if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
            $this->text .= $delta['content'];

            if ($onText !== null) {
                $onText($delta['content']);
            }
        }

        if (is_string($delta['reasoning'] ?? null)) {
            $this->reasoning .= $delta['reasoning'];
        }

        // Each object is kept exactly as it arrived and in order: OpenRouter wants the sequence back unchanged.
        foreach ($delta['reasoning_details'] ?? [] as $detail) {
            if (is_array($detail)) {
                $this->details[] = $detail;
            }
        }

        foreach ($delta['tool_calls'] ?? [] as $fragment) {
            $this->mergeToolCall($fragment);
        }
    }

    /**
     * Fragments of one call share an `index`: id and name arrive first (the first non-empty one
     * wins), `arguments` is a JSON string cut in pieces and only concatenated.
     *
     * @param  array<string, mixed>  $fragment
     */
    private function mergeToolCall(array $fragment): void
    {
        $index = (int) ($fragment['index'] ?? 0);
        $call = $this->calls[$index] ?? ['id' => '', 'name' => '', 'arguments' => ''];

        foreach (['id' => $fragment['id'] ?? null, 'name' => $fragment['function']['name'] ?? null] as $key => $value) {
            if ($call[$key] === '' && is_string($value)) {
                $call[$key] = $value;
            }
        }

        if (is_string($fragment['function']['arguments'] ?? null)) {
            $call['arguments'] .= $fragment['function']['arguments'];
        }

        $this->calls[$index] = $call;
    }
}
