<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

final readonly class AiStreamEvent
{
    public const TYPE_DELTA = 'delta';

    public const TYPE_COMPLETED = 'completed';

    private function __construct(
        public string $type,
        public ?string $text = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    public static function delta(string $text): self
    {
        return new self(self::TYPE_DELTA, text: $text);
    }

    public static function completed(?string $model, ?int $in, ?int $out): self
    {
        return new self(self::TYPE_COMPLETED, model: $model, inputTokens: $in, outputTokens: $out);
    }
}
