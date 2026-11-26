<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Models\AiTurn;
use App\Services\AiAssistant\Costs\AiCallUsage;
use App\Services\AiAssistant\Costs\AiMoney;

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

    public int $inputTokens = 0;

    public int $outputTokens = 0;

    public int $cachedTokens = 0;

    public int $reasoningTokens = 0;

    public string $costUsd = '0';

    public readonly float $startedAt;

    public function __construct(public readonly AiTurn $turn)
    {
        $this->startedAt = microtime(true);
    }

    public function addUsage(AiCallUsage $usage): void
    {
        $this->inputTokens += $usage->inputTokens;
        $this->outputTokens += $usage->outputTokens;
        $this->cachedTokens += $usage->cachedTokens;
        $this->reasoningTokens += $usage->reasoningTokens;
        $this->costUsd = AiMoney::add($this->costUsd, $usage->costUsd ?? '0');
    }

    /** Seconds already spent before a wait for the confirmation: the wait itself does not count. */
    public float $carriedSeconds = 0.0;

    public function elapsedSeconds(): float
    {
        return $this->carriedSeconds + microtime(true) - $this->startedAt;
    }

    /**
     * What the turn row stores: the totals plus the call counters (the ledger keeps the per-call rows).
     *
     * @return array<string, int|string>
     */
    public function usageForStorage(): array
    {
        return [
            'model_calls' => $this->modelCalls,
            'tool_calls' => $this->toolCalls,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cached_tokens' => $this->cachedTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'cost_usd' => bcadd($this->costUsd, '0', 6),
        ];
    }
}
