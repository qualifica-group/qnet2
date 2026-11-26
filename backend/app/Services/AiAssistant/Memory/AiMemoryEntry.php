<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Memory;

/**
 * One message of the model request. Roles: system, user, assistant, tool_call (the model asked for
 * a tool: text = arguments) and tool (the tool answered). `id` is the ai_messages id when the entry
 * comes from a saved message, so a summary can remember how far it reaches.
 */
final readonly class AiMemoryEntry
{
    public function __construct(
        public string $role,
        public string $text,
        public ?string $tool = null,
        public ?string $operation = null,
        public ?int $id = null,
    ) {}
}
