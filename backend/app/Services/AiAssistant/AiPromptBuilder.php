<?php

declare(strict_types=1);

namespace App\Services\AiAssistant;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Attachment;
use App\Services\AiAssistant\Mentions\ResolvedAiMention;
use App\Services\AiAssistant\Prompt\AiChatPrompt;
use App\Services\AiAssistant\Prompt\AiPromptFile;
use App\Services\AiAssistant\Prompt\AiPromptMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles the provider prompt (spec 0347, D-9/D-10/D-11): fixed instructions,
 * page guide, a bounded history as plain text, then the current turn, the only
 * one carrying mention data and file binaries.
 */
final class AiPromptBuilder
{
    /**
     * @param  list<ResolvedAiMention>  $mentions
     */
    public function build(
        AiConversation $conversation,
        AiMessage $current,
        array $mentions,
        ?string $pageGuideText,
        string $locale,
    ): AiChatPrompt {
        return new AiChatPrompt(
            systemInstructions: AiSystemInstructions::for($locale),
            pageGuide: $this->pageGuide($current->page_guide_key, $pageGuideText),
            history: $this->history($conversation, $current),
            current: $this->currentTurn($current, $mentions),
            locale: $locale,
        );
    }

    private function pageGuide(?string $key, ?string $text): ?string
    {
        if (! filled($text)) {
            return null;
        }

        $title = filled($key) ? "Documentation of the page the user is viewing (guide: {$key})" : 'Documentation of the page the user is viewing';

        return "{$title}:\n{$text}";
    }

    /**
     * @return list<AiPromptMessage>
     */
    private function history(AiConversation $conversation, AiMessage $current): array
    {
        return $conversation->messages()
            ->reorder('id', 'desc')
            ->where('id', '<', $current->id)
            ->with('attachments')
            ->limit((int) config('ai-assistant.history_window'))
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message): AiPromptMessage => new AiPromptMessage(
                $message->role->value,
                $this->historicText($message),
            ))
            ->values()
            ->all();
    }

    private function historicText(AiMessage $message): string
    {
        $lines = [$message->content];

        foreach ($message->attachments as $attachment) {
            $lines[] = "[allegato: {$attachment->original_name}]";
        }

        foreach ($message->mentions ?? [] as $mention) {
            $lines[] = "[menzione: {$mention['module']} #{$mention['id']} {$mention['label']}]";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<ResolvedAiMention>  $mentions
     */
    private function currentTurn(AiMessage $message, array $mentions): AiPromptMessage
    {
        $blocks = [$message->content];

        foreach ($mentions as $mention) {
            $blocks[] = "<record module=\"{$mention->module}\" id=\"{$mention->id}\">\n{$mention->context}\n</record>";
        }

        $files = $message->attachments
            ->map(fn (Attachment $attachment): AiPromptFile => new AiPromptFile(
                $attachment->original_name,
                (string) $attachment->mime_type,
                base64_encode((string) Storage::disk($attachment->disk)->get($attachment->path)),
            ))
            ->values()
            ->all();

        return new AiPromptMessage('user', implode("\n\n", $blocks), $files);
    }
}
