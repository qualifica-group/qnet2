<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * The shared read-side filters F (spec 0122, data_contract), validated by
 * `ValidatesTimeEntryFilters` and consumed by the list/overview/pulse
 * endpoints alike — one DTO so the three never drift on what a filter means.
 *
 * `taskTypeIds`/`registryIds`/`opportunityIds`/`workOrderIds`/`taskIds` are
 * ENTRY-level filters (AND across filters, OR of values within one filter,
 * AC-014): applied to the `time_entries` query itself. `dailyStatuses`/
 * `isActive` are DAY-level filters, applied AFTER a day's entries and totals
 * are built (`TimeEntryDayBuilder::filterByActivity`) — they never touch the
 * entries query, since neither is a `time_entries` column.
 */
final readonly class TimeEntryFilterData
{
    /**
     * @param  array<int, int>  $taskTypeIds
     * @param  array<int, int>  $registryIds
     * @param  array<int, int>  $opportunityIds
     * @param  array<int, int>  $workOrderIds
     * @param  array<int, int>  $taskIds
     * @param  array<int, string>  $dailyStatuses
     */
    public function __construct(
        public ?int $userId,
        public ?string $periodPreset,
        public ?string $dateFrom,
        public ?string $dateTo,
        public array $taskTypeIds,
        public array $registryIds,
        public array $opportunityIds,
        public array $workOrderIds,
        public array $taskIds,
        public array $dailyStatuses,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            userId: self::nullableInt($data, 'user_id'),
            periodPreset: self::nullableString($data, 'period_preset'),
            dateFrom: self::nullableString($data, 'date_from'),
            dateTo: self::nullableString($data, 'date_to'),
            taskTypeIds: self::intList($data, 'task_type_ids'),
            registryIds: self::intList($data, 'registry_ids'),
            opportunityIds: self::intList($data, 'opportunity_ids'),
            workOrderIds: self::intList($data, 'work_order_ids'),
            taskIds: self::intList($data, 'task_ids'),
            dailyStatuses: self::stringList($data, 'daily_statuses'),
            isActive: self::nullableBool($data, 'is_active'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private static function intList(array $data, string $key): array
    {
        return array_values(array_map(static fn (mixed $value): int => (int) $value, $data[$key] ?? []));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private static function stringList(array $data, string $key): array
    {
        return array_values(array_map(static fn (mixed $value): string => (string) $value, $data[$key] ?? []));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return isset($data[$key]) ? (int) $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * `"true"|"1"` -> true, `"false"|"0"` -> false, absent -> null
     * (data_contract F: `is_active:"true"|"false"|"1"|"0"`).
     *
     * @param  array<string, mixed>  $data
     */
    private static function nullableBool(array $data, string $key): ?bool
    {
        if (! isset($data[$key])) {
            return null;
        }

        return in_array($data[$key], ['true', '1', true, 1], true);
    }
}
