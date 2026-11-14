<?php

namespace App\Services\Merge;

/**
 * The columns that point at a mergeable record and move to the survivor
 * (spec 0394, D-8/D-9).
 *
 * `plain`: [table, column]. `conflicts`: [table, column, uniqueColumns] — rows
 * colliding on `uniqueColumns` with a survivor row are dropped (D-9).
 * `polymorphic`: [table, typeColumn, idColumn], matched on the entity morph alias.
 */
final readonly class MergeReferences
{
    /**
     * @param  list<array{0: string, 1: string}>  $plain
     * @param  list<array{0: string, 1: string, 2: list<string>}>  $conflicts
     * @param  list<array{0: string, 1: string, 2: string}>  $polymorphic
     */
    public function __construct(
        public array $plain = [],
        public array $conflicts = [],
        public array $polymorphic = [],
    ) {}
}
