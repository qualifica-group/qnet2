<?php

namespace App\DataObjects\Notes;

/**
 * Validated payload for POST /api/notes (spec 0052). Declared DTO — no magic
 * array crosses into NoteService (see standards/architecture.md → Data
 * Transfer Objects).
 */
final readonly class CreateNoteData
{
    /**
     * @param  array<int, int>  $mentionIds
     */
    public function __construct(
        public string $entityType,
        public int $entityId,
        public string $body,
        public ?int $parentId,
        public ?int $quoteId,
        public array $mentionIds,
    ) {}
}
