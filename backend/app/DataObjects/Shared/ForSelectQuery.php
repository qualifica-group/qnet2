<?php

namespace App\DataObjects\Shared;

/**
 * Validated query for a for-select endpoint (GET /api/{resource}/for-select).
 *
 * Declared DTO (no "magic flying array") carrying the search/pagination/hydration
 * inputs from the FormRequest into the Service — see standards/architecture.md →
 * Data Transfer Objects and ADR 0011.
 *
 * - `search`: case-insensitive substring match (null/empty = no filter).
 * - `offset` / `limit`: pagination (limit capped by the FormRequest at MAX_LIMIT).
 * - `ids`: edit-mode hydration — these ids are appended deduplicated, bypass the
 *   search filter and do NOT inflate the total.
 * - `businessFunctionId` (spec 0040 amendment rev.3): ADDITIVE, consumed ONLY
 *   by ProductCategoryService::forSelect — every other for-select consumer
 *   defaults it to null (retrocompatible, no behaviour change).
 * - `operationalSiteId` (spec 0048): ADDITIVE, consumed ONLY by
 *   UserService::forSelect (users/for-select filtered by Sede) — same
 *   retrocompatible pattern as `businessFunctionId`.
 * - `categoryIds` (user directive 2026-07-22): ADDITIVE, consumed ONLY by
 *   ProductService::forSelect (products/for-select scoped to the categories
 *   of an opportunity's product lines) — empty means no scoping, so every
 *   other consumer is unaffected.
 * - `rootCategoryId` (spec 0077): ADDITIVE, consumed ONLY by
 *   ProductCategoryService::forSelect (product-categories/for-select scoped
 *   to a branch root's subtree, INV-1) — same retrocompatible pattern as
 *   `businessFunctionId`.
 */
final readonly class ForSelectQuery
{
    /**
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $categoryIds
     */
    public function __construct(
        public ?string $search,
        public int $offset,
        public int $limit,
        public array $ids,
        public ?int $businessFunctionId = null,
        public ?int $operationalSiteId = null,
        public array $categoryIds = [],
        public ?int $rootCategoryId = null,
    ) {}

    /**
     * Build from a validated for-select FormRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $search = array_key_exists('search', $data) ? trim((string) $data['search']) : null;

        /** @var array<int, int> $ids */
        $ids = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            (array) ($data['ids'] ?? []),
        )));

        /** @var array<int, int> $categoryIds */
        $categoryIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            (array) ($data['category_ids'] ?? []),
        )));

        return new self(
            search: ($search === null || $search === '') ? null : $search,
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 25),
            ids: $ids,
            businessFunctionId: isset($data['business_function_id']) ? (int) $data['business_function_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            categoryIds: $categoryIds,
            rootCategoryId: isset($data['root_category_id']) ? (int) $data['root_category_id'] : null,
        );
    }

    public function hasCategoryIds(): bool
    {
        return $this->categoryIds !== [];
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }

    public function hasIds(): bool
    {
        return $this->ids !== [];
    }
}
