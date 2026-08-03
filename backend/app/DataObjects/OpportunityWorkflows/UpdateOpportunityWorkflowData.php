<?php

namespace App\DataObjects\OpportunityWorkflows;

/**
 * Validated payload for a partial (PATCH) opportunity workflow update
 * (PUT/PATCH /api/opportunity-workflows/{opportunityWorkflow}, spec 0047 Lane
 * A). Declared DTO (no "magic flying array") so the
 * UpdateOpportunityWorkflowRequest -> OpportunityWorkflowService contract is
 * explicit.
 *
 * `criteria`/`statuses`: null = NOT submitted (left untouched); array =
 * submitted, authoritative full-replace sync (mirrors
 * App\Services\OpportunityService::syncProductLines' "null = non inviato /
 * array = autoritativo" convention, spec 0040). `isActiveSubmitted` carries
 * the same not-submitted/submitted-as-false distinction plain nullable
 * booleans can't express on their own (mirrors UpdateOpportunityStatusData's
 * `colorSubmitted`/`groupSubmitted`).
 */
final readonly class UpdateOpportunityWorkflowData
{
    /**
     * @param  ?array<int, array{field: string, value_id: int}>  $criteria
     * @param  ?array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string, system_key_submitted: bool}>  $statuses
     */
    public function __construct(
        public ?string $name = null,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?array $criteria = null,
        public ?array $statuses = null,
    ) {}

    /**
     * Build from the validated UpdateOpportunityWorkflowRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            criteria: array_key_exists('criteria', $data)
                ? CreateOpportunityWorkflowData::normalizeCriteria($data['criteria'])
                : null,
            statuses: array_key_exists('statuses', $data)
                ? self::normalizeStatuses($data['statuses'])
                : null,
        );
    }

    /**
     * Only the workflow's own scalar attributes the client actually
     * submitted, ready for a partial mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }

    public function hasCriteria(): bool
    {
        return $this->criteria !== null;
    }

    public function hasStatuses(): bool
    {
        return $this->statuses !== null;
    }

    /**
     * `system_key` is carried through for one reason only: the optional
     * 'validated' mark (user directive 2026-08-03), which
     * App\Services\OpportunityWorkflows\ValidatedStatusMarker reads to
     * promote/demote a row. The mandatory system rows keep the identity
     * their persisted `id` already carries.
     *
     * `system_key_submitted` carries the not-submitted/submitted-as-null
     * distinction a plain nullable string can't express (same convention as
     * `isActiveSubmitted` above): a payload that never mentions `system_key`
     * — every pre-existing client, seeders included — must NOT be read as
     * "remove the mark".
     *
     * @param  array<int, array{id?: mixed, name: mixed, description?: mixed, color?: mixed, group: mixed, requires_note?: mixed, system_key?: mixed}>  $statuses
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string, system_key_submitted: bool}>
     */
    private static function normalizeStatuses(array $statuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                'name' => (string) $status['name'],
                'description' => array_key_exists('description', $status) ? $status['description'] : null,
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
                'requires_note' => (bool) ($status['requires_note'] ?? false),
                'system_key' => isset($status['system_key']) ? (string) $status['system_key'] : null,
                'system_key_submitted' => array_key_exists('system_key', $status),
            ],
            $statuses,
        );
    }
}
