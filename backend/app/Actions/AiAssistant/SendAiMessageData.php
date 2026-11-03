<?php

declare(strict_types=1);

namespace App\Actions\AiAssistant;

use Illuminate\Http\UploadedFile;

/**
 * Validated input of one chat turn (spec 0347).
 */
final readonly class SendAiMessageData
{
    /**
     * @param  list<array{module: string, id: int}>  $mentions
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public string $content,
        public array $mentions,
        public array $files,
        public ?string $pageGuideKey,
        public ?string $pageGuideText,
        public string $locale,
    ) {}
}
