<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One indicator's computed value for a branch (spec 0106): the branch TOTAL
 * plus the per-GA2 breakdown, `operator_id === null` meaning "Non
 * assegnato".
 */
final class IndicatorResult
{
    /**
     * @param  array<int, array{operator_id: int|null, value: int}>  $byOperator
     * @param  array<int, array{operator_id: int|null, status: string, value: int}>|null  $byOperatorStatus  task 427193, null when not computed
     */
    public function __construct(
        public readonly int $total,
        public readonly array $byOperator,
        public readonly ?array $byOperatorStatus = null,
    ) {}

    /**
     * Current status name => count, for every GA2 ($all) or one of them;
     * null when the breakdown was not computed.
     *
     * @return array<string, int>|null
     */
    public function statusBreakdown(?int $operatorId = null, bool $all = true): ?array
    {
        if ($this->byOperatorStatus === null) {
            return null;
        }

        $counts = [];

        foreach ($this->byOperatorStatus as $row) {
            if ($all || $row['operator_id'] === $operatorId) {
                $counts[$row['status']] = ($counts[$row['status']] ?? 0) + $row['value'];
            }
        }

        return $counts;
    }

    public function valueFor(?int $operatorId): int
    {
        foreach ($this->byOperator as $row) {
            if ($row['operator_id'] === $operatorId) {
                return $row['value'];
            }
        }

        return 0;
    }

    /**
     * @return array<int, int|null>
     */
    public function operatorIds(): array
    {
        return array_column($this->byOperator, 'operator_id');
    }
}
