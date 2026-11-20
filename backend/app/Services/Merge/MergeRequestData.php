<?php

namespace App\Services\Merge;

/**
 * The validated payload of POST /{entity}/merge (spec 0394 data contract).
 */
final readonly class MergeRequestData
{
    public const string COMBINE = 'combine';

    /**
     * @param  list<int>  $mergedIds
     * @param  array<string, int|string>  $fields  field key => record id or `combine`
     * @param  array<string, list<int>>  $discard  collection key => ids to drop
     */
    public function __construct(
        public int $survivorId,
        public array $mergedIds,
        public array $fields = [],
        public array $discard = [],
    ) {}
}
