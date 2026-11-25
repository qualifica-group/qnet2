<?php

declare(strict_types=1);

namespace App\Services\Attributes;

/**
 * Which attribute types may share one grid column (spec 0429, D-2): a legacy
 * field linked to its current one shows in the current one's column, so both
 * must read, filter and sort the same way. Enum and table values differ in
 * vocabulary between the two catalogues: they are never linkable.
 */
final class AttributeLinkRules
{
    /** Type => grid column family. A type missing here cannot be linked. */
    private const array FAMILIES = [
        'date' => 'date',
        'datetime' => 'datetime',
        'text' => 'text',
        'textarea' => 'text',
        'integer' => 'number',
        'decimal' => 'number',
        'boolean' => 'boolean',
    ];

    public static function compatible(string $type, string $otherType): bool
    {
        $family = self::FAMILIES[$type] ?? null;

        return $family !== null && $family === (self::FAMILIES[$otherType] ?? null);
    }
}
