<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * Validated payload for PUT /api/time-entries/day-notes (spec 0122,
 * data_contract). `userId` mirrors TimeEntryData's own: present only when
 * the actor targets someone else's day note, gated by `time-entries.
 * manageAll` in the controller (AC-021) exactly like the generic POST.
 */
final readonly class UpdateDayNoteData
{
    public function __construct(
        public string $date,
        public ?string $note,
        public ?int $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            date: (string) $data['date'],
            note: isset($data['note']) ? (string) $data['note'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
        );
    }
}
