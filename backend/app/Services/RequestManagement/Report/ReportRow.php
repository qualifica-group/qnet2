<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One assembled CSV data row (spec 0106): the already-localized GA2 label
 * ("TOTALE"/a user's name/"Non assegnato") plus the value of EVERY one of
 * the indicator columns (ReportBranchRowsBuilder fills every one).
 * Since spec 0141 D-3, a column not CONFIGURED for the branch is null (empty
 * cell); a configured stub column is 0; a configured real column is the
 * computed value. ReportSheetBuilder/RequestManagementDashboardBuilder read
 * this verbatim, never falling back to a default themselves.
 */
final class ReportRow
{
    /**
     * @param  array<string, int|null>  $values  column key => value|null, every indicator column
     */
    public function __construct(
        public readonly string $label,
        public readonly array $values,
    ) {}
}
