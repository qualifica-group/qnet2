<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which rows a request-management report branch emits (spec 0106 rev-2,
 * D-13): `TotalOnly` emits only the TOTALE row, `OperatorsOnly` emits the
 * GA2 rows plus "Non assegnato" (itself a GA2 row) but never TOTALE, `All`
 * is the pre-revision behaviour. Frozen verbatim in `ExportRun.state` at
 * request time and re-read by the job (AC-035) — never defaulted.
 */
enum RequestManagementReportRowMode: string
{
    case TotalOnly = 'total_only';
    case OperatorsOnly = 'operators_only';
    case All = 'all';
}
