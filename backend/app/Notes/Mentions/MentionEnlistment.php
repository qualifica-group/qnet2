<?php

declare(strict_types=1);

namespace App\Notes\Mentions;

use App\Models\User;
use App\Notes\NoteEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Mentions of people outside a record's perimeter (spec 0353): which
 * mentioned users are outside, whether the author may bring them in (D-5),
 * and the enlisting itself, delegated to the host's EnlistsMentionedUsers.
 */
final class MentionEnlistment
{
    public function __construct(private readonly NoteEntityRegistry $registry) {}

    /**
     * Mentioned ids that are NOT in the record's mentionable perimeter (D-5).
     *
     * @param  array<int, int>  $mentionIds
     * @return array<int, int>
     */
    public function outsideIds(string $entityType, Model $record, array $mentionIds): array
    {
        if ($mentionIds === []) {
            return [];
        }

        $insideIds = $this->registry->mentionableUsersQuery($entityType, $record)
            ->whereIn('id', $mentionIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_diff($mentionIds, $insideIds));
    }

    /**
     * An outsider is admitted only when the module enlists, the author may
     * edit the record and the person is an active, non-service user.
     *
     * @param  array<int, int>  $outsideIds
     *
     * @throws ValidationException 422 on `mentions`
     */
    public function assertEnlistable(User $author, string $entityType, Model $record, array $outsideIds): void
    {
        $enlister = $this->registry->enlisterFor($entityType);

        $admitted = $enlister !== null
            && $enlister->canEnlist($author, $record)
            && User::query()->excludingServiceAccounts()->where('is_active', true)->whereIn('id', $outsideIds)->count() === count($outsideIds);

        if (! $admitted) {
            throw ValidationException::withMessages([
                'mentions' => [__('One or more mentioned users cannot be mentioned on this record.')],
            ]);
        }
    }

    /**
     * Adds the outsiders to the record; returns the ids genuinely added.
     *
     * @param  array<int, int>  $outsideIds
     * @return array<int, int>
     */
    public function enlist(string $entityType, Model $record, array $outsideIds): array
    {
        if ($outsideIds === []) {
            return [];
        }

        return $this->registry->enlisterFor($entityType)?->enlist($record, $outsideIds) ?? [];
    }
}
