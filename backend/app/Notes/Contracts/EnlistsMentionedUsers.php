<?php

declare(strict_types=1);

namespace App\Notes\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Optional capability of a NotableEntity (spec 0353): whoever may edit the
 * record can mention a colleague outside its perimeter, and saving the note
 * enlists that person in the record under the host's own role.
 *
 * The host module declares the role and does the writing; the notes core
 * only asks (AC-021: no module is named in app/Notes/).
 */
interface EnlistsMentionedUsers
{
    public const string ROLE_PARTICIPANT = 'participant';

    public const string ROLE_WATCHER = 'watcher';

    /**
     * Machine name of the role a mentioned outsider is given (one of the
     * ROLE_* constants).
     */
    public function enlistmentRole(): string;

    /**
     * Whether $actor may enlist people in $record (the host's update gate).
     */
    public function canEnlist(User $actor, Model $record): bool;

    /**
     * The permission that lets an enlisted user open the record.
     */
    public function modulePermission(): string;

    /**
     * Adds $userIds to $record under the role; people already holding it are
     * skipped. Must run inside the caller's transaction.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int> the ids genuinely added
     *
     * @throws ValidationException on `mentions` when the role has no room left
     */
    public function enlist(Model $record, array $userIds): array;
}
