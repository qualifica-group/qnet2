<?php

namespace App\DataObjects\WorkOrderWorkflows;

use App\Enums\WorkOrderWorkflowStatusSystemKey;
use Illuminate\Support\Collection;

/**
 * Validated payload for creating a work order workflow (POST
 * /api/work-order-workflows, spec 0322). Twin of CreateQuoteWorkflowData.
 *
 * `criteria` is REQUIRED, min:1: a list of {field, value_id} pairs, one per
 * allow-listed field (App\Support\WorkOrderWorkflows\
 * WorkOrderCriterionFieldRegistry). `statuses` is OPTIONAL. The mandatory
 * system rows (open/completed/closed) are always created by the writer; when
 * the client tags a submitted row with a `system_key`, its descriptive fields
 * SEED that system row, otherwise the writer's defaults apply. Untagged rows
 * are the intermediate CUSTOM rows.
 */
final readonly class CreateWorkOrderWorkflowData
{
    /**
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     * @param  array<int, array{name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $statuses  custom rows only
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $openStatus  descriptive seed for the pinned 'open' row (null = writer default)
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $completedStatus  descriptive seed for the pinned 'completed' row
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $closedStatus  descriptive seed for the pinned 'closed' row
     */
    public function __construct(
        public string $name,
        public bool $isActive,
        public array $criteria,
        public array $statuses,
        public ?array $openStatus = null,
        public ?array $completedStatus = null,
        public ?array $closedStatus = null,
    ) {}

    /**
     * Build from the validated store request payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = $data['statuses'] ?? [];

        return new self(
            name: (string) $data['name'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            criteria: self::normalizeCriteria($data['criteria']),
            statuses: self::normalizeStatuses($statuses),
            openStatus: self::extractSystemStatus($statuses, WorkOrderWorkflowStatusSystemKey::Open),
            completedStatus: self::extractSystemStatus($statuses, WorkOrderWorkflowStatusSystemKey::Completed),
            closedStatus: self::extractSystemStatus($statuses, WorkOrderWorkflowStatusSystemKey::Closed),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @param  array<int, array{field: mixed, value_id: mixed}>  $criteria
     * @return array<int, array{field: string, value_id: int}>
     */
    public static function normalizeCriteria(array $criteria): array
    {
        return array_map(
            static fn (array $criterion): array => [
                'field' => (string) $criterion['field'],
                'value_id' => (int) $criterion['value_id'],
            ],
            $criteria,
        );
    }

    /**
     * The CUSTOM (intermediate) rows only — a submitted row tagged with a
     * `system_key` seeds a pinned row instead (see extractSystemStatus()).
     *
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array<int, array{name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>
     */
    public static function normalizeStatuses(array $statuses): array
    {
        return array_values(array_map(
            static fn (array $status): array => [
                ...self::descriptive($status),
                'group' => (string) $status['group'],
            ],
            array_filter($statuses, static fn (array $status): bool => ($status['system_key'] ?? null) === null),
        ));
    }

    /**
     * The descriptive attributes (name/description/color/progress_percentage)
     * every status row, system or custom, accepts.
     *
     * @param  array<string, mixed>  $status
     * @return array{name: string, description: ?string, color: ?string, progress_percentage: ?int}
     */
    public static function descriptive(array $status): array
    {
        return [
            'name' => (string) $status['name'],
            'description' => $status['description'] ?? null,
            'color' => $status['color'] ?? null,
            'progress_percentage' => isset($status['progress_percentage']) ? (int) $status['progress_percentage'] : null,
        ];
    }

    /**
     * The descriptive seed the client submitted for a pinned system row, or
     * null when it left that row untagged (the writer then uses its default).
     *
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null
     */
    private static function extractSystemStatus(array $statuses, WorkOrderWorkflowStatusSystemKey $key): ?array
    {
        foreach ($statuses as $status) {
            if (($status['system_key'] ?? null) === $key->value) {
                return self::descriptive($status);
            }
        }

        return null;
    }

    /**
     * The deterministic "field:value_id|..." string used to detect two
     * workflows with the same criteria combination: criteria sorted by
     * `field` (each field appears at most once per workflow) before joining,
     * so the payload's submission ORDER never affects the result.
     *
     * @param  array<int, array{field: mixed, value_id: mixed}>  $criteria
     */
    public static function computeSignature(iterable $criteria): string
    {
        return Collection::make($criteria)
            ->map(static fn (array $criterion): array => [
                'field' => (string) $criterion['field'],
                'value_id' => (int) $criterion['value_id'],
            ])
            ->sortBy('field')
            ->values()
            ->map(static fn (array $pair): string => "{$pair['field']}:{$pair['value_id']}")
            ->implode('|');
    }
}
