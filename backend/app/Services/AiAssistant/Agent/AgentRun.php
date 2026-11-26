<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Models\AiTurn;

/**
 * Mutable state of one execution of a turn: what the runner counts against the caps and
 * saves on the turn row as the model answers.
 */
final class AgentRun
{
    /** @var list<array{index: int, tool: string, label: string, state: string}> */
    public array $steps = [];

    public int $modelCalls = 0;

    public int $toolCalls = 0;

    /** Text of the current model round, flushed to the turn row while it streams. */
    public string $text = '';

    public float $lastFlushAt = 0.0;

    /** Set when a save found the turn no longer running (cancelled, deleted): the run stops. */
    public bool $aborted = false;

    public ?string $model = null;

    /** @var array{input_tokens: int, output_tokens: int, cached_tokens: int, reasoning_tokens: int, cost: float} */
    public array $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cached_tokens' => 0, 'reasoning_tokens' => 0, 'cost' => 0.0];

    public readonly float $startedAt;

    public function __construct(public readonly AiTurn $turn)
    {
        $this->startedAt = microtime(true);
    }

    /**
     * @param  array{input_tokens: int, output_tokens: int, cached_tokens: int, reasoning_tokens: int, cost: float}  $usage
     */
    public function addUsage(array $usage): void
    {
        foreach ($usage as $key => $value) {
            $this->usage[$key] += $value;
        }
    }

    public function elapsedSeconds(): float
    {
        return microtime(true) - $this->startedAt;
    }

    /**
     * What the turn row stores: the totals plus the call counters, the cost as a 6-digit decimal string.
     *
     * @return array<string, int|string>
     */
    public function usageForStorage(): array
    {
        return [
            'model_calls' => $this->modelCalls,
            'tool_calls' => $this->toolCalls,
            'input_tokens' => $this->usage['input_tokens'],
            'output_tokens' => $this->usage['output_tokens'],
            'cached_tokens' => $this->usage['cached_tokens'],
            'reasoning_tokens' => $this->usage['reasoning_tokens'],
            'cost_usd' => number_format($this->usage['cost'], 6, '.', ''),
        ];
    }
}
