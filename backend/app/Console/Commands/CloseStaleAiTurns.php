<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiTurn;
use App\Services\AiAssistant\Agent\AiTurnRunner;
use Illuminate\Console\Command;

/**
 * Closes as failed (`turn_timeout`) the turns left queued or running for longer than the longest
 * job plus a margin (spec 0433, state machine): a killed worker would otherwise block the
 * conversation for good. A turn is never handed to the queue again (one attempt, D-8).
 * Each one closes like a job the queue gave up on, so the outcome of a write that was confirmed
 * but never resumed reaches the person as the fixed assistant message (D-18).
 */
class CloseStaleAiTurns extends Command
{
    protected $signature = 'ai-assistant:close-stale-turns';

    protected $description = 'Close the AI assistant turns stuck in the queue or in execution';

    public function handle(AiTurnRunner $runner): int
    {
        $cutoff = now()->subSeconds(RunAiTurn::timeoutSeconds() + (int) config('ai-assistant.agent.stale_margin_seconds'));

        $ids = AiTurn::query()
            ->whereIn('status', [AiTurnStatus::Queued->value, AiTurnStatus::Running->value])
            ->where('updated_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            $runner->fail($id, AiTurnErrorCode::TurnTimeout);
        }

        $this->info("Closed {$ids->count()} stale AI assistant turn(s).");

        return self::SUCCESS;
    }
}
