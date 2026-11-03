<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use Illuminate\Console\Command;

/**
 * Removes the AI assistant conversations idle for longer than the retention
 * window, files included (spec 0347, D-5, AC-017).
 */
class PruneAiConversations extends Command
{
    private const int CHUNK_SIZE = 100;

    protected $signature = 'ai-assistant:prune';

    protected $description = 'Delete AI assistant conversations idle for more than the retention period';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('ai-assistant.retention_days'));
        $deleted = 0;

        // Step 1: walk the expired conversations in chunks (each delete shrinks the set)
        AiConversation::query()
            ->whereRaw('COALESCE(last_message_at, created_at) < ?', [$cutoff])
            ->chunkById(self::CHUNK_SIZE, function ($conversations) use (&$deleted): void {
                // Step 2: delete through Eloquent so attachment files go too
                foreach ($conversations as $conversation) {
                    $conversation->deleteWithFiles();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} AI assistant conversation(s).");

        return self::SUCCESS;
    }
}
