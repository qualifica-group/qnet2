<?php

declare(strict_types=1);

namespace App\DataObjects\Projects;

/**
 * Validated payload for creating a project (POST /api/projects, spec 0023;
 * `code` writable-on-create per spec 0025).
 *
 * Declared DTO (no "magic flying array") so the StoreProjectRequest ->
 * ProjectService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `code` is a manual override (spec 0025, BR-1): null means "let the Service
 * generate PRJ-0001..."; it is deliberately absent from attributes() since
 * it is never in $fillable — the Service assigns it directly, mirroring the
 * generated-code path.
 *
 * `countryId`/`provinceId`/`cityId` (spec 0027, BR-4) default to null at the
 * DTO level only for construction convenience (a pre-existing direct caller,
 * DemoProjectSeeder, does not supply them yet); `country_id` is REQUIRED at
 * the StoreProjectRequest layer, so fromValidated() always fills it from a
 * validated payload.
 *
 * spec 0039, D-3: `pipelineStatusId` is now NULLABLE — an omitted FK falls
 * back to the system_key='new' status, resolved server-side in
 * ProjectService::create() (never here: a DTO stays pure data, no
 * App\Services\Statuses dependency).
 *
 * Spec 0094, D-1/D-2: `businessFunctionId`/`productCategoryId` are REPLACED
 * by `productLines` — a to-many collection, ALWAYS required (min 1) at the
 * StoreProjectRequest layer, delete-all + insert synced by
 * ProjectService::create() inside the same transaction (like
 * CreateOpportunityData's own `productLines`) — so it stays out of
 * attributes().
 */
final readonly class CreateProjectData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines
     */
    public function __construct(
        public ?string $code,
        public string $name,
        public ?int $pipelineStatusId,
        public ?string $description,
        public array $productLines,
        public ?int $stateId,
        public ?int $partnerId,
        public ?int $operationalSiteId,
        public ?string $startDate,
        public ?string $endDate,
        public ?float $totalBudget,
        public ?int $targetLead,
        public ?int $countryId = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
    ) {}

    /**
     * Build from the validated StoreProjectRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: self::nullIfEmpty($data['code'] ?? null),
            name: (string) $data['name'],
            pipelineStatusId: isset($data['pipeline_status_id']) ? (int) $data['pipeline_status_id'] : null,
            description: $data['description'] ?? null,
            productLines: self::normalizeProductLines($data['product_lines'] ?? []),
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            partnerId: isset($data['partner_id']) ? (int) $data['partner_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            totalBudget: isset($data['total_budget']) ? (float) $data['total_budget'] : null,
            targetLead: isset($data['target_lead']) ? (int) $data['target_lead'] : null,
            countryId: isset($data['country_id']) ? (int) $data['country_id'] : null,
            provinceId: isset($data['province_id']) ? (int) $data['province_id'] : null,
            cityId: isset($data['city_id']) ? (int) $data['city_id'] : null,
        );
    }

    /**
     * An empty-string `code` (AC-003) means "no manual code" just as much as
     * an absent one — both fall back to the sequential generator.
     */
    private static function nullIfEmpty(mixed $value): ?string
    {
        return $value === '' ? null : $value;
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
     * The project attributes for a mass-assignment create (framework array
     * boundary). `code` is NOT included: the Service merges it in separately
     * once generated (BR-1). `productLines` is NOT included either: it is a
     * to-many collection synced separately by ProjectService.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'pipeline_status_id' => $this->pipelineStatusId,
            'description' => $this->description,
            'country_id' => $this->countryId,
            'state_id' => $this->stateId,
            'province_id' => $this->provinceId,
            'city_id' => $this->cityId,
            'partner_id' => $this->partnerId,
            'operational_site_id' => $this->operationalSiteId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_budget' => $this->totalBudget,
            'target_lead' => $this->targetLead,
        ];
    }
}
