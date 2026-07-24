<?php

namespace App\DataObjects\OpportunityWorkflows;

use App\Enums\WorkflowStatusSystemKey;
use Illuminate\Support\Collection;

/**
 * Validated payload for creating an opportunity workflow (POST
 * /api/opportunity-workflows, spec 0047 Lane A). Declared DTO (no "magic
 * flying array") so the StoreOpportunityWorkflowRequest ->
 * OpportunityWorkflowService contract is explicit.
 *
 * `criteria` is REQUIRED, min:1 (AC-008): a list of {field, value_id} pairs,
 * one per allow-listed field (App\Support\OpportunityWorkflows\
 * CriterionFieldRegistry). `statuses` is OPTIONAL. The 4 system rows
 * (open/validated/closed_won/closed_lost, AC-004) are always created by the Service via
 * WorkflowStatusWriter; when the client tags a submitted row with a
 * `system_key`, its descriptive fields SEED that system row (the user can
 * fill the pinned rows up front) — otherwise the writer's defaults apply. Untagged
 * rows are the intermediate CUSTOM rows.
 */
final readonly class CreateOpportunityWorkflowData
{
    /**
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     * @param  array<int, array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statuses  custom rows only
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $openStatus  descriptive seed for the pinned 'open' row (null = writer default)
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $validatedStatus  descriptive seed for the pinned 'validated' row (null = writer default)
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $closedWonStatus  descriptive seed for the pinned 'closed_won' row (null = writer default)
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $closedLostStatus  descriptive seed for the pinned 'closed_lost' row (null = writer default)
     */
    public function __construct(
        public string $name,
        public bool $isActive,
        public array $criteria,
        public array $statuses,
        public ?array $openStatus = null,
        public ?array $validatedStatus = null,
        public ?array $closedWonStatus = null,
        public ?array $closedLostStatus = null,
    ) {}

    /**
     * Build from the validated StoreOpportunityWorkflowRequest payload.
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
            openStatus: self::extractSystemStatus($statuses, WorkflowStatusSystemKey::Open),
            validatedStatus: self::extractSystemStatus($statuses, WorkflowStatusSystemKey::Validated),
            closedWonStatus: self::extractSystemStatus($statuses, WorkflowStatusSystemKey::ClosedWon),
            closedLostStatus: self::extractSystemStatus($statuses, WorkflowStatusSystemKey::ClosedLost),
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
     * @param  array<int, array{name: mixed, description?: mixed, color?: mixed, group: mixed, requires_note?: mixed, system_key?: mixed}>  $statuses
     * @return array<int, array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}>
     */
    public static function normalizeStatuses(array $statuses): array
    {
        return array_values(array_map(
            static fn (array $status): array => [
                'name' => (string) $status['name'],
                'description' => array_key_exists('description', $status) ? $status['description'] : null,
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
                'requires_note' => (bool) ($status['requires_note'] ?? false),
            ],
            array_filter($statuses, static fn (array $status): bool => ($status['system_key'] ?? null) === null),
        ));
    }

    /**
     * The descriptive seed (name/description/color/requires_note) the client
     * submitted for a pinned system row, or null when it left that row
     * untagged (the writer then uses its default).
     *
     * @param  array<int, array{name: mixed, description?: mixed, color?: mixed, requires_note?: mixed, system_key?: mixed}>  $statuses
     * @return array{name: string, description: ?string, color: ?string, requires_note: bool}|null
     */
    private static function extractSystemStatus(array $statuses, WorkflowStatusSystemKey $key): ?array
    {
        foreach ($statuses as $status) {
            if (($status['system_key'] ?? null) === $key->value) {
                return [
                    'name' => (string) $status['name'],
                    'description' => array_key_exists('description', $status) ? $status['description'] : null,
                    'color' => array_key_exists('color', $status) ? $status['color'] : null,
                    'requires_note' => (bool) ($status['requires_note'] ?? false),
                ];
            }
        }

        return null;
    }

    /**
     * The deterministic "field:value_id|..." string enforcing criteria-
     * combination uniqueness (AC-009): criteria sorted by `field` (each field
     * appears at most once per workflow, so this is equivalent to sorting the
     * full pair) before joining — the payload's submission ORDER never
     * affects the resulting signature.
     *
     * @param  array<int, array{field: mixed, value_id: mixed}>  $criteria
     */
    public static function computeSignature(array $criteria): string
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
