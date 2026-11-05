<?php

namespace App\DataObjects\Notes;

use App\Models\Note;

/**
 * Outcome of creating or editing a note (spec 0353): the note plus the people
 * the save enlisted in the host record, which the controller reports in
 * `meta.enlisted_user_ids`.
 */
final readonly class NoteSaveResult
{
    /**
     * @param  array<int, int>  $enlistedUserIds
     */
    public function __construct(
        public Note $note,
        public array $enlistedUserIds,
    ) {}
}
