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
    public function build(AiTurn $turn): array
    {
        $current = $turn->userMessage;

        $history = $turn->conversation->messages()
            ->reorder('id', 'desc')
            ->where('id', '<', $current->id)
            ->limit((int) config('ai-assistant.history_window'))
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message): array => ['role' => $message->role->value, 'content' => $this->text($message)])
            ->all();

        return [
            ['role' => 'system', 'content' => AiAgentInstructions::system()],
            ...$history,
            ['role' => 'user', 'content' => $this->currentText($current, $turn)],
        ];
    }

    private function currentText(AiMessage $message, AiTurn $turn): string
    {
        $blocks = [$this->text($message), "<interface_language>{$turn->locale}</interface_language>"];

        if (filled($turn->page_guide_text)) {
            $key = filled($message->page_guide_key) ? " key=\"{$message->page_guide_key}\"" : '';
            $blocks[] = "<page_guide{$key}>\n{$turn->page_guide_text}\n</page_guide>";
        }

        return implode("\n\n", $blocks);
    }

    private function text(AiMessage $message): string
    {
        $lines = [$message->content];

        foreach ($message->mentions ?? [] as $mention) {
            $lines[] = "<mentioned_record type=\"{$mention['module']}\" id=\"{$mention['id']}\"/>";
        }

        return implode("\n", $lines);
    }
}
