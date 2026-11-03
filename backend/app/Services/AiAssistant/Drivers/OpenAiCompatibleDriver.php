<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use App\Services\AiAssistant\Prompt\AiChatPrompt;
use App\Services\AiAssistant\Prompt\AiPromptMessage;
use Generator;

/**
 * Chat Completions streaming for OpenAI and compatible servers (LM Studio...).
 *
 * The output cap is sent as `max_completion_tokens`: OpenAI deprecated
 * `max_tokens` and newer models reject it; compatible servers ignore or accept
 * the new name.
 */
final class OpenAiCompatibleDriver extends AbstractSseChatDriver
{
    private const IMAGE_PREFIX = 'image/';

    protected function driverName(): string
    {
        return AiChatDriverManager::DRIVER_OPENAI;
    }

    protected function url(): string
    {
        return rtrim((string) config('ai-assistant.openai.base_url'), '/').'/chat/completions';
    }

    protected function headers(): array
    {
        $key = config('ai-assistant.openai.api_key');

        return filled($key) ? ['Authorization' => 'Bearer '.$key] : [];
    }

    protected function payload(AiChatPrompt $prompt): array
    {
        $payload = [
            'model' => config('ai-assistant.openai.model'),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'max_completion_tokens' => (int) config('ai-assistant.max_output_tokens'),
            'messages' => $this->messages($prompt),
        ];

        $temperature = config('ai-assistant.temperature');
        if ($temperature !== null) {
            $payload['temperature'] = (float) $temperature;
        }

        return $payload;
    }

    protected function translate(Generator $events): Generator
    {
        $model = null;
        $in = null;
        $out = null;
        $finished = false;

        foreach ($events as $event) {
            $data = $event['data'];

            if ($data === '[DONE]') {
                $finished = true;

                break;
            }

            $model = $data['model'] ?? $model;

            if (isset($data['error'])) {
                $this->fail('provider_error');
            }

            $text = $data['choices'][0]['delta']['content'] ?? null;
            if (is_string($text) && $text !== '') {
                yield AiStreamEvent::delta($text);
            }

            if (($data['choices'][0]['finish_reason'] ?? null) !== null) {
                $finished = true;
            }

            if (isset($data['usage']) && is_array($data['usage'])) {
                $in = $data['usage']['prompt_tokens'] ?? $in;
                $out = $data['usage']['completion_tokens'] ?? $out;
            }
        }

        if (! $finished) {
            $this->fail('truncated_stream');
        }

        yield AiStreamEvent::completed($model, $in, $out);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(AiChatPrompt $prompt): array
    {
        $system = $prompt->systemInstructions;
        if (filled($prompt->pageGuide)) {
            $system .= "\n\n".$prompt->pageGuide;
        }

        $messages = [['role' => 'system', 'content' => $system]];

        foreach ([...$prompt->history, $prompt->current] as $message) {
            $messages[] = ['role' => $message->role, 'content' => $this->content($message)];
        }

        return $messages;
    }

    /**
     * @return string|list<array<string, mixed>>
     */
    private function content(AiPromptMessage $message): string|array
    {
        if ($message->files === []) {
            return $message->text;
        }

        $parts = [];
        foreach ($message->files as $file) {
            $dataUri = "data:{$file->mimeType};base64,{$file->base64}";
            $parts[] = str_starts_with($file->mimeType, self::IMAGE_PREFIX)
                ? ['type' => 'image_url', 'image_url' => ['url' => $dataUri]]
                : ['type' => 'file', 'file' => ['filename' => $file->name, 'file_data' => $dataUri]];
        }
        $parts[] = ['type' => 'text', 'text' => $message->text];

        return $parts;
    }
}
