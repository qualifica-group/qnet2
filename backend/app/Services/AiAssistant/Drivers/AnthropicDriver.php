<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use App\Services\AiAssistant\Prompt\AiChatPrompt;
use App\Services\AiAssistant\Prompt\AiPromptMessage;
use Generator;

/**
 * Anthropic Messages API streaming. The system prompt is sent as text blocks
 * with the cache breakpoint on the last one (stable prefix, D-11).
 */
final class AnthropicDriver extends AbstractSseChatDriver
{
    private const IMAGE_PREFIX = 'image/';

    private const MESSAGE_SEPARATOR = "\n\n";

    protected function driverName(): string
    {
        return AiChatDriverManager::DRIVER_ANTHROPIC;
    }

    protected function url(): string
    {
        return rtrim((string) config('ai-assistant.anthropic.base_url'), '/').'/messages';
    }

    protected function headers(): array
    {
        return [
            'x-api-key' => (string) config('ai-assistant.anthropic.api_key'),
            'anthropic-version' => (string) config('ai-assistant.anthropic.version'),
            'content-type' => 'application/json',
        ];
    }

    protected function payload(AiChatPrompt $prompt): array
    {
        $payload = [
            'model' => config('ai-assistant.anthropic.model'),
            'stream' => true,
            'max_tokens' => (int) config('ai-assistant.max_output_tokens'),
            'system' => $this->system($prompt),
            'messages' => array_map($this->message(...), $this->normalized($prompt)),
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

        foreach ($events as $event) {
            $data = $event['data'];
            if (! is_array($data)) {
                continue;
            }

            switch ($data['type'] ?? null) {
                case 'message_start':
                    $model = $data['message']['model'] ?? $model;
                    $in = $data['message']['usage']['input_tokens'] ?? $in;
                    $out = $data['message']['usage']['output_tokens'] ?? $out;
                    break;
                case 'content_block_delta':
                    $text = $data['delta']['text'] ?? null;
                    if (($data['delta']['type'] ?? null) === 'text_delta' && is_string($text) && $text !== '') {
                        yield AiStreamEvent::delta($text);
                    }
                    break;
                case 'message_delta':
                    $out = $data['usage']['output_tokens'] ?? $out;
                    break;
                case 'message_stop':
                    yield AiStreamEvent::completed($model, $in, $out);

                    return;
                case 'error':
                    $this->fail('provider_error');
            }
        }

        $this->fail('truncated_stream');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function system(AiChatPrompt $prompt): array
    {
        $blocks = [['type' => 'text', 'text' => $prompt->systemInstructions]];
        if (filled($prompt->pageGuide)) {
            $blocks[] = ['type' => 'text', 'text' => $prompt->pageGuide];
        }

        $blocks[array_key_last($blocks)]['cache_control'] = ['type' => 'ephemeral'];

        return $blocks;
    }

    /**
     * The API needs a first user message and strictly alternating roles:
     * drop leading assistant turns and merge consecutive same-role turns
     * (e.g. history left with a user turn whose answer was rolled back).
     *
     * @return list<AiPromptMessage>
     */
    private function normalized(AiChatPrompt $prompt): array
    {
        $result = [];

        foreach ([...$prompt->history, $prompt->current] as $message) {
            if ($result === [] && $message->role !== 'user') {
                continue;
            }

            $last = array_key_last($result);
            if ($last !== null && $result[$last]->role === $message->role) {
                $result[$last] = new AiPromptMessage(
                    $message->role,
                    $result[$last]->text.self::MESSAGE_SEPARATOR.$message->text,
                    [...$result[$last]->files, ...$message->files],
                );

                continue;
            }

            $result[] = $message;
        }

        return $result;
    }

    /**
     * @return array{role: string, content: string|list<array<string, mixed>>}
     */
    private function message(AiPromptMessage $message): array
    {
        if ($message->files === []) {
            return ['role' => $message->role, 'content' => $message->text];
        }

        $blocks = [];
        foreach ($message->files as $file) {
            $isImage = str_starts_with($file->mimeType, self::IMAGE_PREFIX);
            $blocks[] = [
                'type' => $isImage ? 'image' : 'document',
                'source' => ['type' => 'base64', 'media_type' => $file->mimeType, 'data' => $file->base64],
            ];
        }
        $blocks[] = ['type' => 'text', 'text' => $message->text];

        return ['role' => $message->role, 'content' => $blocks];
    }
}
