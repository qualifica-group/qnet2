<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Services\Tasks\TaskDefaultLookupResolver;
use App\Services\Tasks\TaskInitialStatusResolver;

/**
 * Status, type, priority, importance and category of an imported task (spec
 * 0289, D-6, AC-003/AC-004): the legacy sends names, this resolves them to
 * qnet-2 rows. An unknown or absent type/priority/importance takes the
 * lookup's default, an unknown category stays empty, and an absent or
 * unknown status is the one qnet-2 derives on create. A warning is added only
 * when a name WAS sent and not found.
 */
final class LegacyTaskClassifier
{
    public function __construct(
        private readonly LegacyTaskLookups $lookups,
        private readonly TaskDefaultLookupResolver $defaults,
        private readonly TaskInitialStatusResolver $initialStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, string>  $warnings
     * @return array{task_status_id: int, task_type_id: ?int, task_priority_id: ?int, task_importance_id: ?int, task_category_id: ?int}
     */
    public function classify(array $record, array $assigneeIds, int $creatorId, ?int $requesterId, array &$warnings): array
    {
        return [
            'task_status_id' => $this->statusId($record, $assigneeIds, $creatorId, $requesterId, $warnings),
            'task_type_id' => $this->named($record['type_name'] ?? null, $this->lookups->typeIdByName(...), $this->defaults->taskTypeId(...), 'task type', $warnings),
            'task_priority_id' => $this->named($record['priority_name'] ?? null, $this->lookups->priorityIdByName(...), $this->defaults->taskPriorityId(...), 'task priority', $warnings),
            'task_importance_id' => $this->named($record['importance_name'] ?? null, $this->lookups->importanceIdByName(...), $this->defaults->taskImportanceId(...), 'task importance', $warnings),
            'task_category_id' => $this->named($record['category_name'] ?? null, $this->lookups->categoryIdByName(...), static fn (): ?int => null, 'task category', $warnings),
        ];
    }

    /**
     * D-6: the protected row of the system key, else the row of that name,
     * else the status qnet-2 derives on create.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, string>  $warnings
     */
    private function statusId(array $record, array $assigneeIds, int $creatorId, ?int $requesterId, array &$warnings): int
    {
        $systemKey = $this->text($record['status_system_key'] ?? null);
        $statusName = $this->text($record['status_name'] ?? null);

        $statusId = match (true) {
            $systemKey !== null => $this->lookups->statusIdBySystemKey($systemKey),
            $statusName !== null => $this->lookups->statusIdByName($statusName),
            default => null,
        };

        if ($statusId === null && ($systemKey !== null || $statusName !== null)) {
            $warnings[] = 'Unknown task status "'.($systemKey ?? $statusName).'"; the status was derived.';
        }

        return $statusId ?? $this->initialStatus->resolveForCreate(null, $assigneeIds, $creatorId, $requesterId);
    }

    /**
     * @param  callable(string): ?int  $byName
     * @param  callable(): ?int  $default
     * @param  array<int, string>  $warnings
     */
    private function named(mixed $name, callable $byName, callable $default, string $what, array &$warnings): ?int
    {
        $name = $this->text($name);

        if ($name === null) {
            return $default();
        }

        $id = $byName($name);

        if ($id === null) {
            $warnings[] = "Unknown {$what} \"{$name}\"; ".($default() === null ? 'left empty.' : 'the default was used.');
        }

        return $id ?? $default();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
