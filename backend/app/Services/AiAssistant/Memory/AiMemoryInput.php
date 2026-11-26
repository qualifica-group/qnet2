<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Memory;

/**
 * Everything the memory needs to shape one request. `entries` are the history NOT yet covered by
 * `summary`, oldest first, ending with the message that starts the turn and, in the loop, the tool
 * exchange of the turn in progress. `tools` are the tool definitions (name, description, parameters).
 */
final readonly class AiMemoryInput
{
    /**
     * @param  list<array<string, mixed>>  $tools
     * @param  list<AiMemoryEntry>  $entries
     */
    public function __construct(
        public string $core,
        public string $instructions,
        public array $tools,
        public array $entries,
        public ?string $summary,
        public int $summaryCount,
        public int $contextWindow,
        /** False when the provider takes the tools in their own request field: they then weigh on the budget but are not a message. */
        public bool $toolsInMessages = true,
    ) {}
}
