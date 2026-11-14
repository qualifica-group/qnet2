<?php

namespace App\Services\Merge;

use InvalidArgumentException;

/**
 * Resolves the merge definition of an entity key (`registries`, `referents`).
 */
final class MergeDefinitionRegistry
{
    /** @var array<string, class-string<MergeDefinition>> */
    private const array DEFINITIONS = [
        'registries' => RegistryMergeDefinition::class,
        'referents' => ReferentMergeDefinition::class,
    ];

    public function for(string $entity): MergeDefinition
    {
        $class = self::DEFINITIONS[$entity] ?? throw new InvalidArgumentException("No merge definition for [{$entity}].");

        return app($class);
    }
}
