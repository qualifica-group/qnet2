<?php

declare(strict_types=1);

namespace App\Jobs\AiAssistant;

use App\Services\AiAssistant\Preview\AiPreviewDecisions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delayed to the expiry of a write preview (spec 0433, D-17): resumes the turn with «expired» only if
 * the turn still waits for THIS preview, so the person can close the browser. One attempt, and a job
 * that finds the turn already moved does nothing (D-18).
 */
final class ExpireAiPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $turnId, public readonly string $previewId)
    {
        $this->onQueue(RunAiTurn::QUEUE);
        $this->afterCommit();
    }

    public function handle(AiPreviewDecisions $decisions): void
    {
        $decisions->expire($this->turnId, $this->previewId);
    }
}
