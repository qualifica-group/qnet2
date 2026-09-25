<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement\Concerns;

use Illuminate\Validation\Rule;

/**
 * The report's period, shared by POST /report and GET /report/dashboard (spec
 * 0169 D-2): both bounds OPTIONAL `Y-m-d`, an absent one meaning "no limit on
 * that side". `date_to >= date_from` only applies when both are present:
 * `after_or_equal:date_from` against a missing field would read the literal
 * "date_from" as a date and always fail.
 */
trait ValidatesReportDateRange
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reportDateRangeRules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
        ];
    }

    /** Inclusive lower bound, or NULL for "from the beginning". */
    public function dateFrom(): ?string
    {
        $date = $this->validated('date_from');

        return $date === null ? null : (string) $date;
    }

    /** Inclusive upper bound, or NULL for "with no end". */
    public function dateTo(): ?string
    {
        $date = $this->validated('date_to');

        return $date === null ? null : (string) $date;
    }
}
