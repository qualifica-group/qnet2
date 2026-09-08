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
     */
    public function __construct(
        public readonly int $total,
        public readonly array $byOperator,
    ) {}

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
