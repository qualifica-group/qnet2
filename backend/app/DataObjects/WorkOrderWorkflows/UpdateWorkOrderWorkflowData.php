<?php

namespace App\DataObjects\WorkOrderWorkflows;

/**
 * Validated payload for a partial (PATCH) work order workflow update
 * (PUT/PATCH /api/work-order-workflows/{workOrderWorkflow}, spec 0322). Twin
 * of UpdateQuoteWorkflowData.
 *
 * `criteria`/`statuses`: null = NOT submitted (left untouched); array =
 * submitted, authoritative full-replace sync. `isActiveSubmitted` carries the
 * same not-submitted/submitted-as-false distinction a nullable boolean can't
 * express on its own.
 */
final readonly class UpdateWorkOrderWorkflowData
{
    /**
     * @param  ?array<int, array{field: string, value_id: int}>  $criteria
     * @param  ?array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $statuses
     */
    public function __construct(
        public ?string $name = null,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?array $criteria = null,
        public ?array $statuses = null,
    ) {}

    /**
     * Build from the validated update request payload.
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
                ? CreateWorkOrderWorkflowData::normalizeCriteria($data['criteria'])
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
     * A submitted `system_key` is deliberately DROPPED here: on update every
     * system row is identified by its persisted `id`, and no key can be
     * claimed or released by a payload.
     *
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>
     */
    private static function normalizeStatuses(array $statuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                ...CreateWorkOrderWorkflowData::descriptive($status),
                'group' => (string) $status['group'],
            ],
            $statuses,
        );
    }
}
