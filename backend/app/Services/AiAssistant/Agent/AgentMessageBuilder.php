<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Models\AiMessage;
use App\Models\AiTurn;
use App\Services\AiAssistant\Memory\AiConversationMemory;
use App\Services\AiAssistant\Memory\AiMemoryEntry;
use App\Services\AiAssistant\Memory\AiMemoryInput;
use App\Services\AiAssistant\Memory\AiMemoryPlan;

/**
 * Messages sent to the model for a turn (spec 0433, D-5): the memory plans them (fixed core, then
 * the summary, then the history, then the person's message) and this class feeds it and turns
 * its plan into the messages of the request. A mention reaches the model as a type and an
 * identifier only (D-2); the page guide and the interface language ride on the current message
 * so the system prompt, and with it the cacheable prefix, never changes.
 */
final class AgentMessageBuilder
{
    public function __construct(private readonly AiConversationMemory $memory) {}

    /**
     * @param  list<array<string, mixed>>  $tools  sent in their own request field, not as a message
     */
    public function input(AiTurn $turn, string $additionalInstructions, array $tools, int $contextWindow): AiMemoryInput
    {
        $conversation = $turn->conversation;

        return new AiMemoryInput(
            core: AiAgentInstructions::core(),
            instructions: AiAgentInstructions::additionalBlock($additionalInstructions),
            tools: $tools,
            entries: $this->entries($turn),
            summary: $conversation->summary,
            summaryCount: $conversation->summary_count,
            contextWindow: $contextWindow,
            toolsInMessages: false,
        );
    }

    /**
     * The planned entries as the messages of the request. System entries merge into one message;
     * the lines that stand for the tool results of earlier turns ride on the answer of that turn,
     * so no `tool` message appears without the call it answers.
     *
     * @return list<array<string, mixed>>
     */
    public function request(AiMemoryPlan $plan): array
    {
        $messages = [];
        $notes = [];

        foreach ($plan->messages as $entry) {
            if ($entry->role === 'tool') {
                $notes[] = $entry->text;

                continue;
            }

            if ($entry->text === '') {
                continue;
            }

            if ($entry->role === 'assistant' && $notes !== []) {
                $entry = new AiMemoryEntry('assistant', implode("\n", [...$notes, $entry->text]));
                $notes = [];
            } elseif ($notes !== []) {
                $messages[] = ['role' => 'assistant', 'content' => implode("\n", $notes)];
                $notes = [];
            }

            $last = array_key_last($messages);

            if ($entry->role === 'system' && $last !== null && $messages[$last]['role'] === 'system') {
                $messages[$last]['content'] .= "\n\n".$entry->text;
            } else {
                $messages[] = ['role' => $entry->role, 'content' => $entry->text];
            }
        }

        return $messages;
    }

    /**
     * The saved history not yet covered by the summary, each past turn followed by one entry per
     * tool it used (the memory turns those into a line), then the current message.
     *
     * @return list<AiMemoryEntry>
     */
    private function entries(AiTurn $turn): array
    {
        $current = $turn->userMessage;
        $history = array_values(array_filter(
            $this->memory->entriesOf($turn->conversation),
            fn (AiMemoryEntry $entry): bool => $entry->id < $current->id,
        ));

        $steps = AiTurn::query()
            ->where('ai_conversation_id', $turn->ai_conversation_id)
            ->where('id', '<', $turn->id)
            ->whereIn('user_message_id', array_map(fn (AiMemoryEntry $entry): ?int => $entry->id, $history))
            ->get(['id', 'user_message_id', 'steps'])
            ->mapWithKeys(fn (AiTurn $earlier): array => [$earlier->user_message_id => $earlier->steps ?? []]);

        $entries = [];
        foreach ($history as $entry) {
            $entries[] = $entry;

            foreach ($steps[$entry->id] ?? [] as $step) {
                $entries[] = new AiMemoryEntry('tool', '', $step['tool'], AssistantTools::historyOperation($step['operation'] ?? null), $entry->id);
            }
        }

        $entries[] = new AiMemoryEntry('user', $this->currentText($current, $turn), id: $current->id);

        return $entries;
    }

    private function currentText(AiMessage $message, AiTurn $turn): string
    {
        $blocks = [$message->content, '<interface_language>'.$this->xml($turn->locale, attribute: false).'</interface_language>'];

        foreach ($message->mentions ?? [] as $mention) {
            $blocks[] = '<record module="'.$this->xml($mention['module']).'" id="'.$this->xml($mention['id']).'"/>';
        }

        if (filled($turn->page_guide_text)) {
            $key = filled($message->page_guide_key) ? ' key="'.$this->xml($message->page_guide_key).'"' : '';
            $blocks[] = "<page_guide{$key}>\n".$this->xml($turn->page_guide_text, attribute: false)."\n</page_guide>";
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Text that is not the person's message is data: no value can close a tag or open another.
     * Double quotes are escaped only inside attributes, so apostrophes and quotes of a guide stay readable.
     */
    private function xml(mixed $value, bool $attribute = true): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ($attribute ? ENT_COMPAT : ENT_NOQUOTES), 'UTF-8');
    }
}
