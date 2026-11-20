<?php

namespace App\Services\Merge;

/**
 * One element of a discardable collection (spec 0394, D-6). `matchKey` identifies
 * the same information across records (null = never a duplicate).
 */
final readonly class MergeItem
{
    public function __construct(
        public int $id,
        public int $recordId,
        public string $label,
        public ?string $detail,
        public ?string $matchKey,
    ) {}
}
