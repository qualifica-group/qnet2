<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One assembled CSV data row (spec 0106): the already-localized GA2 label
 * ("TOTALE"/a user's name/"Non assegnato") plus the value of EVERY one of
 * the eleven indicator columns (ReportBranchRowsBuilder fills every one,
 * defaulting to 0 for a column not applicable to the branch — D-15, rev-2,
 * overrides the former D-9 empty-cell distinction). ReportSheetBuilder never
 * needs to fall back to a default itself.
 */
final class ReportRow
{
    /**
     * @param  array<string, int>  $values  column key => value, every indicator column
     */
    public function __construct(
        public readonly string $label,
        public readonly array $values,
    ) {}
}
