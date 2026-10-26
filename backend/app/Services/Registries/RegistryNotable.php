<?php

declare(strict_types=1);

namespace App\Services\Registries;

use App\Models\Registry;
use App\Notes\PermissionGatedNotable;
use Illuminate\Database\Eloquent\Model;

/**
 * The `registries` notable_types descriptor (spec 0312): the notes thread of an
 * anagrafica, readable with `registries.view` like the record itself
 * (RegistryPolicy::view has no other condition).
 */
final class RegistryNotable extends PermissionGatedNotable
{
    public function modelClass(): string
    {
        return Registry::class;
    }

    public function label(Model $record): string
    {
        /** @var Registry $record */
        return (string) $record->name;
    }

    protected function viewPermission(): string
    {
        return 'registries.view';
    }

    protected function detailPath(Model $record): string
    {
        return '/registries/'.$record->getKey();
    }
}
