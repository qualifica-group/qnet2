<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Memory;

/**
 * The outcome of AiConversationMemory::plan(). `messages` is the request to send. When
 * `needsSummary` is true, `entriesToSummarize` are the old entries to fold into the summary before
 * sending (see AiConversationMemory::summarize()). `proposeNewConversation` tells the turn to
 * offer a fresh conversation to the person.
 */
final readonly class AiMemoryPlan
{
    /**
     * @param  list<AiMemoryEntry>  $messages
     * @param  list<AiMemoryEntry>  $entriesToSummarize
     */
    public function __construct(
        public array $messages,
        public int $estimatedTokens,
        public int $thresholdTokens,
        public bool $needsSummary,
        public array $entriesToSummarize,
        public bool $proposeNewConversation,
    ) {}
}
