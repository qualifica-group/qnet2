<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The name of a lookup row, for a report that can be read without ids. Each table
 * is read once: the alignment never writes the lookups it names here.
 */
final class OperationalLookupNames
{
    /** @var array<class-string<Model>, array<int|string, string>> */
    private array $names = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function name(string $modelClass, mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $this->names[$modelClass] ??= $modelClass::query()->pluck('name', 'id')->all();

        return $this->names[$modelClass][$id] ?? (string) $id;
    }
}
