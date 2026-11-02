<?php

declare(strict_types=1);

namespace App\Services\Assignment;

/**
 * What one actor's assignment competence reaches, as the record-visibility
 * tier `viewCompetence` reads it (spec 0340): either EVERY record of the
 * module (the `covers_all_product_categories` flag, D-3 — records without any
 * category included) or the records carrying at least one category of
 * $categoryIds (D-7: the descendant closure and the per-function wildcard
 * already expanded by OperatorCompetence::reachOf()). Empty when the actor has
 * no competence at all: the tier then adds nothing.
 */
final readonly class CompetenceReach
{
    /**
     * @param  list<int>  $categoryIds  ascending, unique
     */
    private function __construct(
        public bool $all,
        public array $categoryIds,
    ) {}

    public static function none(): self
    {
        return new self(false, []);
    }

    public static function everything(): self
    {
        return new self(true, []);
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public static function of(array $categoryIds): self
    {
        $ids = array_values(array_unique(array_map(intval(...), $categoryIds)));
        sort($ids);

        return new self(false, $ids);
    }

    public function isEmpty(): bool
    {
        return ! $this->all && $this->categoryIds === [];
    }

    /**
     * The in-memory counterpart of the scopes' `whereIn`: whether a record
     * carrying $recordCategoryIds is in reach. The flag answers true even for
     * a record with no category (D-3).
     *
     * @param  array<int, int>  $recordCategoryIds
     */
    public function reaches(array $recordCategoryIds): bool
    {
        if ($this->all) {
            return true;
        }

        return array_intersect(array_map(intval(...), $recordCategoryIds), $this->categoryIds) !== [];
    }

    /**
     * Cache-key identity of this reach: two actors with the same fingerprint
     * reach the same records. Hashed because the list can be long and keys are
     * capped at 255 chars.
     */
    public function fingerprint(): string
    {
        if ($this->all) {
            return 'all';
        }

        return $this->categoryIds === [] ? '' : sha1(implode(',', $this->categoryIds));
    }
}
