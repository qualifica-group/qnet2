<?php

namespace App\Tables\Users;

use App\Authorization\UsersAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The users-grid columns carrying contractual data (spec 0283 D-4), each tied
 * to the `users` field key whose visibility it follows: a column the actor's
 * field matrix hides leaves the catalogue, the filters (hence the sort/filter
 * allow-lists and the export) and the rows.
 */
final class UserSensitiveColumns
{
    /**
     * Grid column => `users` field key. The employment status is derived from
     * the termination date, so it follows that field.
     *
     * @var array<string, string>
     */
    private const array FIELD_BY_COLUMN = [
        'company' => 'employment.company_id',
        'relationship_type' => 'employment.relationship_type',
        'hired_at' => 'employment.hired_at',
        'terminated_at' => 'employment.terminated_at',
        'employment_status' => 'employment.terminated_at',
    ];

    /**
     * Memoized per actor: mapRow() asks once per row.
     *
     * @var array<int, array<int, string>>
     */
    private array $hiddenByActor = [];

    public function __construct(private readonly UsersAuthorization $authorization) {}

    /**
     * Drops the catalogue entries (columns or filters) the authenticated user
     * may not see. The user comes from the auth guard (set by the request and
     * by the export job), as the catalogue methods take no actor.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function filterCatalogue(array $entries, string $idKey): array
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            return $entries;
        }

        $hidden = $this->hiddenColumns($actor);

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ! in_array($entry[$idKey], $hidden, true),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function hiddenColumns(User $actor): array
    {
        return $this->hiddenByActor[$actor->id] ??= $this->resolveHidden($actor);
    }

    /**
     * @return array<int, string>
     */
    private function resolveHidden(User $actor): array
    {
        $fields = $this->authorization->fieldPermissions($actor, null);

        return array_keys(array_filter(
            self::FIELD_BY_COLUMN,
            static fn (string $field): bool => ! ($fields[$field]->visible ?? false),
        ));
    }
}
