<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether an actor may see the legacy `old_id` of a record: super-admin
 * (Gate::before) or a role holding `legacy-ids.view` (LegacyIdPolicy). The
 * single check shared by the grid column, the grid rows and the record
 * Resources, so the id is never sent to an actor who may not see it.
 */
final class LegacyIdVisibility
{
    public const string PERMISSION = 'legacy-ids.view';

    public static function allows(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }

    /**
     * The mapped table row with the legacy id behind the default `old_id`
     * column (InjectsDefaultIdColumn) attached, for every row that carries it
     * and only for an actor who may see it: shared by the grid rows and the
     * export, so no mapRow repeats it.
     *
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    public static function attachToRow(array $mapped, Model $row, ?User $user): array
    {
        if (array_key_exists('old_id', $row->getAttributes()) && self::allows($user)) {
            $mapped['old_id'] ??= $row->getAttribute('old_id');
        }

        return $mapped;
    }
}
