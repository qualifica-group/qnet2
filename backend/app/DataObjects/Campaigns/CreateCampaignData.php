<?php

declare(strict_types=1);

namespace App\DataObjects\Campaigns;

/**
 * Validated payload for creating a campaign (POST /api/campaigns, spec 0023;
 * `code` writable-on-create per spec 0025).
 *
 * Declared DTO (no "magic flying array") so the StoreCampaignRequest ->
 * CampaignService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `code` is a manual override (spec 0025, BR-1): null means "let the Service
 * generate CMP-0001..."; it is deliberately absent from attributes() since
 * it is never in $fillable — the Service assigns it directly, mirroring the
 * generated-code path.
 *
 * `countryId`/`provinceId`/`cityId` (spec 0027, BR-5) default to null at the
 * DTO level only for construction convenience (a pre-existing direct caller,
 * DemoCampaignSeeder, does not supply them yet). Unlike `pipelineStatusId`,
 * geo forcing when linked is NOT decided here (attributes() only knows
 * `project_id`, not which specific levels the project fills) — that is
 * CampaignService's job (it has the loaded Project row).
 *
 * Spec 0094, D-1/D-2: `businessFunctionId`/`productCategoryId` are REPLACED
 * by `productLines` — a to-many collection, null when linked (BR-2:
 * `prohibited` at the FormRequest layer, so simply absent from $data),
 * required (min 1) when standalone. Synced by CampaignService::create()
 * inside the same transaction, so it stays out of attributes() like
 * `pipelineStatusId`'s BR-2 forcing.
 */
final readonly class CreateCampaignData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>|null  $productLines
     */
    public function __construct(
        public ?string $code,
        public ?int $projectId,
        public string $name,
        public ?string $description,
        public ?int $partnerId,
        public ?int $operationalSiteId,
        public ?int $pipelineStatusId,
        public ?array $productLines,
        public ?int $stateId,
        public ?string $startDate,
        public ?string $endDate,
        public ?float $totalBudget,
        public ?int $targetLead,
        public ?int $countryId = null,
        public ?int $provinceId = null,
        public ?int $cityId = null,
    ) {}

    /**
     * Build from the validated StoreCampaignRequest payload. `pipeline_status_id`/
     * `product_lines` are validated `prohibited` when `project_id` is set
     * (BR-2), so they are simply absent from $data in that case.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: self::nullIfEmpty($data['code'] ?? null),
            projectId: isset($data['project_id']) ? (int) $data['project_id'] : null,
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            partnerId: isset($data['partner_id']) ? (int) $data['partner_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            pipelineStatusId: isset($data['pipeline_status_id']) ? (int) $data['pipeline_status_id'] : null,
            productLines: array_key_exists('product_lines', $data) ? self::normalizeProductLines($data['product_lines']) : null,
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
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
     * Whether this campaign is linked to a project (BR-2).
     */
    public function isLinkedToProject(): bool
    {
        return $this->projectId !== null;
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
     * The campaign attributes for a mass-assignment create (framework array
     * boundary). `code` is NOT included: the Service merges it in separately
     * once generated (BR-1). `productLines` is NOT included either: it is a
     * to-many collection synced separately by CampaignService, null when
     * linked (BR-2). `pipeline_status_id` is forced null here when linked, as
     * defence in depth on top of the FormRequest's `prohibited` rule. The 4
     * geo fields are passed through AS SUBMITTED — they are validated
     * `prohibited` per-level by the FormRequest already, and CampaignService
     * additionally nulls out (defence in depth) whatever level the linked
     * project fills (BR-5), which requires the loaded Project row this DTO
     * does not have.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $linked = $this->isLinkedToProject();

        return [
            'project_id' => $this->projectId,
            'name' => $this->name,
            'description' => $this->description,
            'partner_id' => $this->partnerId,
            'operational_site_id' => $this->operationalSiteId,
            'pipeline_status_id' => $linked ? null : $this->pipelineStatusId,
            'country_id' => $this->countryId,
            'state_id' => $this->stateId,
            'province_id' => $this->provinceId,
            'city_id' => $this->cityId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_budget' => $this->totalBudget,
            'target_lead' => $this->targetLead,
        ];
    }
}
