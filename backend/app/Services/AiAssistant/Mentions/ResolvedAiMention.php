<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

/**
 * A mentioned record that passed the authorization: the person sees `label`
 * (stored on the message); the provider gets only `module` and `id`.
 */
final readonly class ResolvedAiMention
{
    public function __construct(
        public string $module,
        public int $id,
        public string $label,
    ) {}
}
