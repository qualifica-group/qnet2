<?php

namespace App\Services\Merge;

/**
 * Where a module links to a mergeable record (spec 0394, D-8), for the preview
 * `linked` block. `ownerColumns` are qualified `table.column` names whose value
 * is the record id; `joins` (table, local, foreign) reach rows that follow the
 * record through its opportunities and are therefore never reassigned.
 * `typeColumn`/`typeAlias` restrict a polymorphic recipient column.
 */
final readonly class LinkedSource
{
    /**
     * @param  list<string>  $ownerColumns
     * @param  list<array{0: string, 1: string, 2: string}>  $joins
     */
    public function __construct(
        public string $key,
        public string $table,
        public array $ownerColumns,
        public array $joins = [],
        public ?string $typeColumn = null,
        public ?string $typeAlias = null,
    ) {}
}
