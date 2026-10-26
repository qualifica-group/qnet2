<?php

declare(strict_types=1);

namespace App\Services\Referents;

use App\Models\Referent;
use App\Notes\PermissionGatedNotable;
use Illuminate\Database\Eloquent\Model;

/**
 * The `referents` notable_types descriptor (spec 0312): the notes thread of a
 * referent, readable with `referents.view` like the record itself
 * (ReferentPolicy::view has no other condition).
 */
final class ReferentNotable extends PermissionGatedNotable
{
    public function modelClass(): string
    {
        return Referent::class;
    }

    public function label(Model $record): string
    {
        /** @var Referent $record */
        return (string) $record->name;
    }

    protected function viewPermission(): string
    {
        return 'referents.view';
    }

    protected function detailPath(Model $record): string
    {
        return '/referents/'.$record->getKey();
    }
}
