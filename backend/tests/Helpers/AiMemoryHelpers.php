<?php

use App\Services\AiAssistant\Memory\AiMemoryEntry;
use App\Services\AiAssistant\Memory\AiMemoryInput;
use App\Services\AiAssistant\Memory\AiSummaryExecutor;

/** @return list<array<string, mixed>> */
function aim_tools(): array
{
    return [
        ['name' => 'run_code', 'description' => 'Run', 'parameters' => ['b' => 1, 'a' => 2]],
        ['name' => 'confirm', 'description' => 'Confirm', 'parameters' => []],
    ];
}

/** @param list<AiMemoryEntry> $entries */
function aim_input(array $entries, ?string $summary = null, int $summaryCount = 0, int $window = 100000): AiMemoryInput
{
    return new AiMemoryInput(
        core: 'CORE TEXT',
        instructions: 'EXTRA TEXT',
        tools: aim_tools(),
        entries: $entries,
        summary: $summary,
        summaryCount: $summaryCount,
        contextWindow: $window,
    );
}

/** @param list<AiMemoryEntry> $entries */
function aim_json(array $entries): string
{
    return json_encode(array_map(fn (AiMemoryEntry $e): array => (array) $e, $entries), JSON_THROW_ON_ERROR);
}

/** An executor that answers `$reply` and keeps the request it received in `$seen`. */
function aim_executor(string $reply, ?array &$seen = null): AiSummaryExecutor
{
    return new class($reply, $seen) implements AiSummaryExecutor
    {
        public function __construct(private string $reply, private ?array &$seen) {}

        public function complete(array $request): string
        {
            $this->seen = $request;

            return $this->reply;
        }
    };
}
