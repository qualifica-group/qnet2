<?php

namespace App\DataObjects\Table;

/**
 * Result of an SSRM rows query: the mapped page of rows plus the pagination
 * metadata the response envelope needs.
 *
 * Declared DTO (no "magic flying array") so the Service <-> Controller contract is
 * explicit, type-safe and refactor-proof — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `items` stays an array because it is the dynamic per-domain row payload
 * (the definition's mapRow() output) that is serialized as-is by TableRowResource.
 */
final readonly class RowsResult
{
    /**
     * @param  array<int, array<string, mixed>>  $items  mapped rows (each + its actions[])
     * @param  array<string, int|float|string|null>  $aggregates  spec 0156, D-3 — empty for a domain with no aggregates
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $limit,
        public array $aggregates = [],
    ) {}
}
