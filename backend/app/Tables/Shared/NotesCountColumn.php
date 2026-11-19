<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The `notes_count` badge of the `notes` row action: roots AND replies of the
 * thread the action opens (soft-deleted excluded by Note's own scope), only
 * those the actor has not read yet (Note::unreadBy, TracksReads).
 */
final class NotesCountColumn
{
    public const string FIELD = 'notes_count';

    /**
     * `withCount` argument over the row model's notes relation (`notes`,
     * `noteThread`, `scopedNotes`: whichever thread the action opens).
     *
     * @return array<string, callable(Builder<Note>): void>
     */
    public static function of(string $relation): array
    {
        return [$relation.' as '.self::FIELD => fn (Builder $query) => $query->unreadBy((int) Auth::id())];
    }

    public static function project(Model $row): int
    {
        return (int) ($row->getAttribute(self::FIELD) ?? 0);
    }
}
