<?php

declare(strict_types=1);

namespace App\DataObjects\Projects;

/**
 * Validated payload for a partial (PATCH) project update
 * (PUT/PATCH /api/projects/{project}, spec 0023).
 *
 * Declared DTO (no "magic flying array") so the UpdateProjectRequest ->
 * ProjectService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `name`/`pipelineStatusId` are mandatory fields (BR-5/D-5): they cannot
 * legitimately be nulled out, so a plain non-null property is enough (mirrors
 * UpdateProductData's `name`). Every other FK/scalar is a legitimately
 * nullable VALUE (clearing the classification), so a plain null property
 * cannot distinguish "not submitted" from "submitted as null" — the
 * `*Submitted` flags carry that distinction, mirroring
 * UpdateBusinessFunctionData/UpdateProductData. `code` is never accepted
 * (BR-1): there is no property for it at all.
 *
 * Spec 0094, D-1/D-2: `businessFunctionId`/`productCategoryId` are REPLACED
 * by `productLines` — a full-replace to-many collection, null-means-
 * untouched like `managerSlots` on UpdateOpportunityData: omitted, the
 * project's rows are left as-is; present, ProjectService::update() syncs the
 * whole collection. Out of submittedAttributes() (not a mass-assignable
 * column).
 */
final readonly class UpdateProjectData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>|null  $productLines
     */
    public function __construct(
        public ?string $name = null,
        public ?int $pipelineStatusId = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?array $productLines = null,
        public ?int $countryId = null,
        public bool $countryIdSubmitted = false,
        public ?int $stateId = null,
        public bool $stateIdSubmitted = false,
        public ?int $provinceId = null,
        public bool $provinceIdSubmitted = false,
        public ?int $cityId = null,
        public bool $cityIdSubmitted = false,
        public ?int $partnerId = null,
        public bool $partnerIdSubmitted = false,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?string $endDate = null,
        public bool $endDateSubmitted = false,
        public ?float $totalBudget = null,
        public bool $totalBudgetSubmitted = false,
        public ?int $targetLead = null,
        public bool $targetLeadSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateProjectRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            pipelineStatusId: array_key_exists('pipeline_status_id', $data) ? (int) $data['pipeline_status_id'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            productLines: array_key_exists('product_lines', $data) ? self::normalizeProductLines($data['product_lines']) : null,
            countryId: self::nullableInt($data, 'country_id'),
            countryIdSubmitted: array_key_exists('country_id', $data),
            stateId: self::nullableInt($data, 'state_id'),
            stateIdSubmitted: array_key_exists('state_id', $data),
            provinceId: self::nullableInt($data, 'province_id'),
            provinceIdSubmitted: array_key_exists('province_id', $data),
            cityId: self::nullableInt($data, 'city_id'),
            cityIdSubmitted: array_key_exists('city_id', $data),
            partnerId: self::nullableInt($data, 'partner_id'),
            partnerIdSubmitted: array_key_exists('partner_id', $data),
            operationalSiteId: self::nullableInt($data, 'operational_site_id'),
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
            startDate: array_key_exists('start_date', $data) ? $data['start_date'] : null,
            startDateSubmitted: array_key_exists('start_date', $data),
            endDate: array_key_exists('end_date', $data) ? $data['end_date'] : null,
            endDateSubmitted: array_key_exists('end_date', $data),
            totalBudget: array_key_exists('total_budget', $data) && $data['total_budget'] !== null ? (float) $data['total_budget'] : null,
            totalBudgetSubmitted: array_key_exists('total_budget', $data),
            targetLead: array_key_exists('target_lead', $data) && $data['target_lead'] !== null ? (int) $data['target_lead'] : null,
            targetLeadSubmitted: array_key_exists('target_lead', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary). `code` never
     * appears (BR-1).
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->pipelineStatusId !== null) {
            $attributes['pipeline_status_id'] = $this->pipelineStatusId;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->countryIdSubmitted) {
            $attributes['country_id'] = $this->countryId;
        }

        if ($this->stateIdSubmitted) {
            $attributes['state_id'] = $this->stateId;
        }

        if ($this->provinceIdSubmitted) {
            $attributes['province_id'] = $this->provinceId;
        }

        if ($this->cityIdSubmitted) {
            $attributes['city_id'] = $this->cityId;
        }

        if ($this->partnerIdSubmitted) {
            $attributes['partner_id'] = $this->partnerId;
        }

        if ($this->operationalSiteIdSubmitted) {
            $attributes['operational_site_id'] = $this->operationalSiteId;
        }

        if ($this->startDateSubmitted) {
            $attributes['start_date'] = $this->startDate;
        }

        if ($this->endDateSubmitted) {
            $attributes['end_date'] = $this->endDate;
        }

        if ($this->totalBudgetSubmitted) {
            $attributes['total_budget'] = $this->totalBudget;
        }

        if ($this->targetLeadSubmitted) {
            $attributes['target_lead'] = $this->targetLead;
        }

        return $attributes;
    }

    public function hasProductLines(): bool
    {
        return $this->productLines !== null;
    }

    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private static function normalizeProductLines(mixed $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'business_function_id' => (int) $row['business_function_id'],
                'product_category_id' => (int) $row['product_category_id'],
            ],
            (array) $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null ? (int) $data[$key] : null;
    }
}
