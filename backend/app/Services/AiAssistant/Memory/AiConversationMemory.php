<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Memory;

use App\Models\AiConversation;
use App\Models\AiMessage;
use LogicException;
use RuntimeException;

/**
 * Memory of one assistant conversation (spec 0433, D-5): shapes the model request, decides when the
 * old part must be summarised and when to propose a new conversation. It never calls the model: the
 * turn passes an AiSummaryExecutor, so the call goes through the turn's driver and cost ledger.
 *
 * The request is, in this order: core instructions, additional instructions, tool definitions
 * (sorted, so the bytes do not depend on the caller), the summary, then the messages. The first
 * three never change inside a conversation and the summary changes only when it is rewritten, so
 * provider prompt caches keep applying.
 */
final class AiConversationMemory
{
    private const string OMITTED_RESULT = '[Earlier tool result omitted: %s. Read the data again with the tool if it is needed.]';

    private const string SUMMARY_HEADER = "Summary of the earlier part of this conversation:\n";

    private const string SUMMARY_INSTRUCTIONS = <<<'TEXT'
You condense the earlier part of a conversation between a person and a business assistant into a summary that replaces it.
- Keep the decisions made, the records mentioned (each by type and identifier) and the operations performed with their outcomes.
- Never invent anything that is not in the text.
- Never write masked or redacted values in clear: if a value appears masked, keep it masked or leave it out.
- Do not copy rows of data: they are read again with the tools when needed.
- If a previous summary is given, merge it with the new part into one summary.
- Write in the language of the conversation, in at most %d words.
TEXT;

    /** Tokens added per message for role markers and framing. */
    private const int MESSAGE_OVERHEAD_TOKENS = 4;

    /**
     * Plan the next model request.
     *
     * Token estimate: UTF-8 bytes divided by `memory.bytes_per_token` (initial value 2.0), plus
     * MESSAGE_OVERHEAD_TOKENS per message. Real tokenizers average 3-4 bytes per token on Italian
     * and English prose and about 2.5 on JSON, so 2.0 overestimates on purpose (an upper bound for
     * these languages, not for pathological text such as emoji runs). The true window is read back
     * from the provider's usage by the turn; this estimate only decides when to summarise.
     */
    public function plan(AiMemoryInput $input): AiMemoryPlan
    {
        // Step 1: earlier turns lose tool calls and tool results; the current turn stays whole
        $entries = $this->compactEarlierTurns($input->entries);

        // Step 2: fixed prefix, then summary, then messages
        $messages = [...$this->prefix($input), ...$entries];

        // Step 3: budget against the threshold; tools sent apart still take room in the window
        $estimated = $this->estimateTokens($messages) + ($input->toolsInMessages ? 0 : $this->estimateTokens([new AiMemoryEntry('system', $this->toolsBlock($input->tools))]));
        $threshold = (int) floor($input->contextWindow * (int) config('ai-assistant.memory.summary_threshold_percent') / 100);
        $old = $this->entriesOlderThanRecentTurns($entries);

        return new AiMemoryPlan(
            messages: $messages,
            estimatedTokens: $estimated,
            thresholdTokens: $threshold,
            needsSummary: $estimated > $threshold && $old !== [],
            entriesToSummarize: $old,
            proposeNewConversation: $this->proposesNewConversation($input->summaryCount),
        );
    }

    /** True once a conversation has been summarised the configured number of times. */
    public function proposesNewConversation(int $summaryCount): bool
    {
        return $summaryCount >= (int) config('ai-assistant.memory.max_summaries');
    }

    /** The saved messages of this conversation not yet covered by its summary, oldest first. */
    public function entriesOf(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->where('id', '>', (int) $conversation->summary_through_message_id)
            ->get()
            ->map(fn (AiMessage $message): AiMemoryEntry => new AiMemoryEntry(
                $message->role->value,
                $this->savedText($message),
                id: $message->id,
            ))
            ->all();
    }

    /**
     * The request that asks the model to fold `$entries` (and the previous summary) into one summary.
     *
     * @param  list<AiMemoryEntry>  $entries
     * @return list<AiMemoryEntry>
     */
    public function buildSummaryRequest(array $entries, ?string $previousSummary): array
    {
        $transcript = [];
        if (filled($previousSummary)) {
            $transcript[] = "Previous summary:\n{$previousSummary}\n";
        }
        $transcript[] = 'New part to fold in:';
        foreach ($entries as $entry) {
            $transcript[] = "[{$entry->role}] {$entry->text}";
        }

        return [
            new AiMemoryEntry('system', sprintf(self::SUMMARY_INSTRUCTIONS, (int) config('ai-assistant.memory.summary_max_words'))),
            new AiMemoryEntry('user', implode("\n", $transcript)),
        ];
    }

    /**
     * Fold the old part of the plan into the conversation summary through the injected executor and
     * save it. Returns the new summary.
     */
    public function summarize(AiConversation $conversation, AiMemoryPlan $plan, AiSummaryExecutor $executor): string
    {
        $throughId = collect($plan->entriesToSummarize)->pluck('id')->filter()->max();
        if ($throughId === null) {
            throw new LogicException('Only saved messages can be summarised.');
        }

        $summary = trim($executor->complete($this->buildSummaryRequest($plan->entriesToSummarize, $conversation->summary)));
        if ($summary === '') {
            throw new RuntimeException('The summary came back empty.');
        }

        $conversation->update([
            'summary' => $summary,
            'summary_count' => $conversation->summary_count + 1,
            'summary_through_message_id' => $throughId,
        ]);

        return $summary;
    }

    /** Tool and, when there is one, operation of an omitted result. */
    private function describeResult(AiMemoryEntry $entry): string
    {
        return 'tool='.$entry->tool.($entry->operation === null ? '' : ' operation='.$entry->operation);
    }

    /** @return list<AiMemoryEntry> */
    private function prefix(AiMemoryInput $input): array
    {
        $prefix = [
            new AiMemoryEntry('system', $input->core),
            new AiMemoryEntry('system', $input->instructions),
        ];
        if ($input->toolsInMessages) {
            $prefix[] = new AiMemoryEntry('system', $this->toolsBlock($input->tools));
        }
        if (filled($input->summary)) {
            $prefix[] = new AiMemoryEntry('system', self::SUMMARY_HEADER.$input->summary);
        }

        return $prefix;
    }

    /** @param  list<array<string, mixed>>  $tools */
    private function toolsBlock(array $tools): string
    {
        usort($tools, fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return json_encode(['tools' => $this->sortKeys($tools)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map($this->sortKeys(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  list<AiMemoryEntry>  $entries
     * @return list<AiMemoryEntry>
     */
    private function compactEarlierTurns(array $entries): array
    {
        $currentStart = $this->lastUserIndex($entries);
        $compact = [];
        foreach ($entries as $index => $entry) {
            if ($index >= $currentStart || ! in_array($entry->role, ['tool_call', 'tool'], true)) {
                $compact[] = $entry;
            } elseif ($entry->role === 'tool') {
                $compact[] = new AiMemoryEntry('tool', sprintf(self::OMITTED_RESULT, $this->describeResult($entry)), $entry->tool, $entry->operation, $entry->id);
            }
        }

        return $compact;
    }

    /**
     * Everything before the last `memory.keep_recent_turns` turns (a turn starts at a user entry).
     *
     * @param  list<AiMemoryEntry>  $entries
     * @return list<AiMemoryEntry>
     */
    private function entriesOlderThanRecentTurns(array $entries): array
    {
        $starts = array_keys(array_filter($entries, fn (AiMemoryEntry $e): bool => $e->role === 'user'));
        $keep = max(1, (int) config('ai-assistant.memory.keep_recent_turns'));
        $cut = count($starts) > $keep ? $starts[count($starts) - $keep] : 0;

        return array_slice($entries, 0, $cut);
    }

    /** @param  list<AiMemoryEntry>  $entries */
    private function lastUserIndex(array $entries): int
    {
        $last = 0;
        foreach ($entries as $index => $entry) {
            if ($entry->role === 'user') {
                $last = $index;
            }
        }

        return $last;
    }

    /** @param  list<AiMemoryEntry>  $messages */
    private function estimateTokens(array $messages): int
    {
        $bytesPerToken = max(1.0, (float) config('ai-assistant.memory.bytes_per_token'));

        return array_sum(array_map(
            fn (AiMemoryEntry $m): int => (int) ceil(strlen($m->text) / $bytesPerToken) + self::MESSAGE_OVERHEAD_TOKENS,
            $messages,
        ));
    }

    /** A past message as plain text: a mention is only a marker with the type and the identifier. */
    private function savedText(AiMessage $message): string
    {
        $lines = [$message->content];
        foreach ($message->mentions ?? [] as $mention) {
            $lines[] = "[menzione: {$mention['module']} #{$mention['id']}]";
        }

        return implode("\n", $lines);
    }
}
