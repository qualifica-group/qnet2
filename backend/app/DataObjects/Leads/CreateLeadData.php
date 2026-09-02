<?php

declare(strict_types=1);

namespace App\DataObjects\Leads;

/**
 * Validated payload for creating a lead (POST /api/leads, spec 0024).
 *
 * Declared DTO (no "magic flying array") so the StoreLeadRequest ->
 * LeadService contract is explicit — see standards/architecture.md → Data
 * Transfer Objects. `registry_id`/`campaign_id` are mandatory (BR-1, spec
 * 0041 D-1); no `code` field exists for a Lead (D-3). `extra_fields` (spec
 * 0033) is an optional free-form key/value store, also populated by
 * LeadsImportDefinition::persistRow() for imported rows.
 *
 * Lead status is derived from assignment/opportunity state and is not accepted
 * in the write contract.
 *
 * `convertToOpportunity` (spec 0044) is a request-level flag, NOT a Lead
 * attribute: it drives LeadService::create()'s conversion branch and is
 * deliberately absent from attributes() — it must never reach
 * `Lead::create()`'s mass assignment.
 *
 * `stateId` (Regione, spec 0047 / directive 2026-07-21) is a user input:
 * `stateIdSubmitted` carries whether the client actually sent it, so
 * LeadService can honour a submitted value (including an explicit null) and
 * fall back to deriving it from the Sede only when the key was absent. Like
 * `state_id` on the DB, it is set by the Service's overlay, not through
 * attributes().
 *
 * `productsOfInterest` (spec 0094, D-5) follows the SAME null-means-
 * untouched convention as CreateOpportunityData's own field: synced by
 * App\Services\Leads\LeadProductInterestWriter, never mass-assigned, so it
 * stays out of attributes().
 */
final readonly class CreateLeadData
{
    /**
     * @param  array<string, string>|null  $extraFields
     * @param  array<int, int>|null  $productsOfInterest
     */
    public function __construct(
        public int $registryId,
        public int $campaignId,
        public ?int $operationalSiteId,
        public ?int $sourceId,
        public ?int $operatorId,
        public ?string $notes,
        public ?array $extraFields = null,
        public bool $convertToOpportunity = false,
        public ?int $stateId = null,
        public bool $stateIdSubmitted = false,
        public ?array $productsOfInterest = null,
    ) {}

    /**
     * Build from the validated StoreLeadRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            registryId: (int) $data['registry_id'],
            campaignId: (int) $data['campaign_id'],
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            operatorId: isset($data['operator_id']) ? (int) $data['operator_id'] : null,
            notes: $data['notes'] ?? null,
            extraFields: $data['extra_fields'] ?? null,
            convertToOpportunity: (bool) ($data['convert_to_opportunity'] ?? false),
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            stateIdSubmitted: array_key_exists('state_id', $data),
            productsOfInterest: array_key_exists('products_of_interest', $data) ? self::normalizeIds($data['products_of_interest']) : null,
        );
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn ($id): int => (int) $id, (array) $ids)));
    }

    public function hasProductsOfInterest(): bool
    {
        return $this->productsOfInterest !== null;
    }

    /**
     * The lead attributes for a mass-assignment create (framework array
     * boundary). `state_id` is intentionally excluded — LeadService overlays
     * it (submitted value or Sede-derived fallback).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'registry_id' => $this->registryId,
            'campaign_id' => $this->campaignId,
            'operational_site_id' => $this->operationalSiteId,
            'source_id' => $this->sourceId,
            'operator_id' => $this->operatorId,
            'notes' => $this->notes,
            'extra_fields' => $this->extraFields,
        ];
    }
}
