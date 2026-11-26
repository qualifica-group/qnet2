<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Jobs\AiAssistant\RunAiTurn;
use App\Models\AiTurn;
use Illuminate\Console\Command;

/**
 * Closes as failed (`turn_timeout`) the turns left queued or running for longer than the longest
 * job plus a margin (spec 0433, state machine): a killed worker would otherwise block the
 * conversation for good. A turn is never handed to the queue again (one attempt, D-8).
 */
class CloseStaleAiTurns extends Command
{
    protected $signature = 'ai-assistant:close-stale-turns';

    protected $description = 'Close the AI assistant turns stuck in the queue or in execution';

    public function handle(): int
    {
        $cutoff = now()->subSeconds(RunAiTurn::timeoutSeconds() + (int) config('ai-assistant.agent.stale_margin_seconds'));

        $closed = AiTurn::query()
            ->whereIn('status', [AiTurnStatus::Queued->value, AiTurnStatus::Running->value])
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => AiTurnStatus::Failed,
                'error_code' => AiTurnErrorCode::TurnTimeout,
                'finished_at' => now(),
            ]);

        $this->info("Closed {$closed} stale AI assistant turn(s).");

        return self::SUCCESS;
    }
}
