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
 * - `statusGroups` (directive 2026-08-31 rev.2): ADDITIVE, consumed ONLY by
 *   ContractStatusService::forSelect (contract-statuses/for-select narrowed
 *   to the groups a given contract action may move to) — empty means no
 *   filter, so every other consumer is unaffected.
 * - `quoteId` (spec 0093, D-8): ADDITIVE, consumed ONLY by
 *   QuoteOfferLineForSelectService (quote-offer-lines/for-select scoped to
 *   ONE quote's own REVENUE lines) — REQUIRED by that endpoint's own
 *   FormRequest, but null by default so every other consumer is unaffected.
 * - `exceptWorkOrderId` (spec 0095, D-7): ADDITIVE, consumed ONLY by
 *   QuoteOfferLineForSelectService — excludes lines already programmed into
 *   ANOTHER work order (D-4), but readmits the ones belonging to THIS one,
 *   so the work-order edit form keeps offering its own already-selected
 *   rows. Null by default (no exclusion widening) so every other consumer
 *   is unaffected.
 * - `competenceCategoryIds` (spec 0110): ADDITIVE, consumed ONLY by
 *   UserService::forSelect (users/for-select narrowed to the operators
 *   competent for a record's required categories, INV-3/INV-4) — empty means
 *   no filter, so every other consumer is unaffected. It only ever NARROWS
 *   (INV-5): it is applied as an exclusion on top of the existing filters,
 *   never as a widening OR.
 * - `includeInactive` (spec 0101, T-03c): ADDITIVE, consumed ONLY by the five
 *   Task configurator services, which otherwise serve `is_active = true`
 *   rows only. The reorder sheet needs EVERY row, active or not: the server
 *   validates `ordered_ids` against the full set, so a list missing the
 *   deactivated rows is rejected as incomplete. False by default, so every
 *   other consumer keeps its current filtering.
 */
final readonly class ForSelectQuery
{
    /**
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $statusGroups
     * @param  array<int, int>  $competenceCategoryIds
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
        public array $statusGroups = [],
        public ?int $quoteId = null,
        public ?int $exceptWorkOrderId = null,
        public bool $includeInactive = false,
        public array $competenceCategoryIds = [],
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

        /** @var array<int, int> $competenceCategoryIds */
        $competenceCategoryIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            (array) ($data['competence_category_ids'] ?? []),
        )));

        /** @var array<int, string> $statusGroups */
        $statusGroups = array_values(array_unique(array_map(
            static fn ($group): string => (string) $group,
            (array) ($data['status_groups'] ?? []),
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
            statusGroups: $statusGroups,
            quoteId: isset($data['quote_id']) ? (int) $data['quote_id'] : null,
            exceptWorkOrderId: isset($data['except_work_order_id']) ? (int) $data['except_work_order_id'] : null,
            includeInactive: (bool) ($data['include_inactive'] ?? false),
            competenceCategoryIds: $competenceCategoryIds,
        );
    }

    public function hasCompetenceCategoryIds(): bool
    {
        return $this->competenceCategoryIds !== [];
    }

    public function hasCategoryIds(): bool
    {
        return $this->categoryIds !== [];
    }

    public function hasStatusGroups(): bool
    {
        return $this->statusGroups !== [];
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
