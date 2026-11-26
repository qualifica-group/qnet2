<?php

declare(strict_types=1);

namespace App\Jobs\AiAssistant;

use App\Enums\AiTurnErrorCode;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one agent turn on the `ai-assistant` queue (spec 0433, D-8): one attempt, because a
 * repeated turn would double costs and previews. The job timeout is the longest the turn can
 * last, and it must stay under the `retry_after` of the queue connection (a test checks it).
 */
final class RunAiTurn implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'ai-assistant';

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $turnId)
    {
        $this->onQueue(self::QUEUE);
        $this->afterCommit();
        $this->timeout = self::timeoutSeconds();
    }

    /**
     * Turn duration cap plus the longest single call that can overrun it (model, then connector).
     */
    public static function timeoutSeconds(): int
    {
        return (int) config('ai-assistant.agent.max_turn_seconds')
            + (int) config('ai-assistant.timeout_seconds')
            + (int) config('ai-assistant.connector.timeout_seconds');
    }

    public function handle(AiTurnRunner $runner): void
    {
        $runner->run($this->turnId);
    }

    /**
     * The worker was killed or timed out: the runner never throws, so this is a crash.
     */
    public function failed(?Throwable $exception): void
    {
        app(AiTurnRunner::class)->fail($this->turnId, AiTurnErrorCode::Internal);
    }
}
