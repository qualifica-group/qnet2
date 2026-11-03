<?php

namespace App\Http\Requests\AiAssistant;

use App\Actions\AiAssistant\SendAiMessageData;
use App\Models\AiConversation;
use App\Services\AiAssistant\Mentions\AiMentionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\Mime\MimeTypes;

/**
 * One chat turn (spec 0347). Authorization runs first (owner of the
 * conversation), so another user's conversation answers 403 before any
 * validation detail. Every limit comes from config/ai-assistant.php.
 */
class StoreAiMessageRequest extends FormRequest
{
    public const array LOCALES = ['it', 'en'];

    private const int GUIDE_KEY_MAX_CHARS = 100;

    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiConversation
            && ($this->user()?->can('update', $conversation) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $mimes = (array) config('ai-assistant.accepted_mimes');

        return [
            'content' => ['required', 'string', 'max:'.(int) config('ai-assistant.max_message_chars')],
            'mentions' => ['sometimes', 'array', 'max:'.(int) config('ai-assistant.max_mentions')],
            'mentions.*.module' => ['required', 'string', Rule::in(app(AiMentionRegistry::class)->modules())],
            'mentions.*.id' => ['required', 'integer', 'min:1'],
            'files' => $mimes === []
                ? ['prohibited']
                : ['sometimes', 'array', 'max:'.(int) config('ai-assistant.max_files')],
            'files.*' => $mimes === [] ? [] : [
                'file',
                'mimetypes:'.implode(',', $mimes),
                'extensions:'.implode(',', $this->extensionsFor($mimes)),
                'max:'.(int) config('ai-assistant.max_file_size_kb'),
            ],
            'page_guide_key' => ['nullable', 'string', 'max:'.self::GUIDE_KEY_MAX_CHARS],
            'page_guide_text' => ['nullable', 'string', 'max:'.(int) config('ai-assistant.guide_max_chars')],
            'locale' => ['required', Rule::in(self::LOCALES)],
        ];
    }

    public function toData(): SendAiMessageData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->all();

        return new SendAiMessageData(
            content: (string) $validated['content'],
            mentions: array_values(array_map(
                static fn (array $mention): array => ['module' => (string) $mention['module'], 'id' => (int) $mention['id']],
                $validated['mentions'] ?? [],
            )),
            files: array_values(array_filter($validated['files'] ?? [], static fn (mixed $file): bool => $file instanceof UploadedFile)),
            pageGuideKey: $validated['page_guide_key'] ?? null,
            pageGuideText: $validated['page_guide_text'] ?? null,
            locale: (string) $validated['locale'],
        );
    }

    /**
     * File extensions matching the accepted MIME types (spoof defence next to the MIME rule).
     *
     * @param  array<int, string>  $mimes
     * @return list<string>
     */
    private function extensionsFor(array $mimes): array
    {
        $types = MimeTypes::getDefault();

        return array_values(array_unique(array_merge(...array_map(
            static fn (string $mime): array => $types->getExtensions($mime),
            $mimes,
        ))));
    }
}
