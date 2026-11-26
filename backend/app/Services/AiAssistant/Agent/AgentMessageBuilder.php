<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use App\Models\AiMessage;
use App\Models\AiTurn;

/**
 * Messages sent to the model for a turn (spec 0433): the fixed system prompt, a bounded
 * history as plain text, then the person's message. A mention reaches the model as a type and
 * an identifier only (D-2); the page guide and the interface language ride on the current message
 * so the system prompt, and with it the cacheable prefix, never changes.
 */
final class AgentMessageBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(AiTurn $turn, string $additionalInstructions): array
    {
        $current = $turn->userMessage;

        $history = $turn->conversation->messages()
            ->reorder('id', 'desc')
            ->where('id', '<', $current->id)
            ->limit((int) config('ai-assistant.history_window'))
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message): array => ['role' => $message->role->value, 'content' => $this->historicText($message)])
            ->all();

        return [
            ['role' => 'system', 'content' => AiAgentInstructions::system($additionalInstructions)],
            ...$history,
            ['role' => 'user', 'content' => $this->currentText($current, $turn)],
        ];
    }

    private function currentText(AiMessage $message, AiTurn $turn): string
    {
        $blocks = [$message->content, "<interface_language>{$turn->locale}</interface_language>"];

        foreach ($message->mentions ?? [] as $mention) {
            $blocks[] = "<record module=\"{$mention['module']}\" id=\"{$mention['id']}\"/>";
        }

        if (filled($turn->page_guide_text)) {
            $key = filled($message->page_guide_key) ? " key=\"{$message->page_guide_key}\"" : '';
            $blocks[] = "<page_guide{$key}>\n{$turn->page_guide_text}\n</page_guide>";
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Past messages as plain text: a mention is only a marker with the type and the identifier,
     * and a file only its name (the model reads the record again through the tools).
     */
    private function historicText(AiMessage $message): string
    {
        $lines = [$message->content];

        foreach ($message->mentions ?? [] as $mention) {
            $lines[] = "[menzione: {$mention['module']} #{$mention['id']}]";
        }

        return implode("\n", $lines);
    }
}
