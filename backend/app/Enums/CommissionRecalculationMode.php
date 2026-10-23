<?php

namespace App\Enums;

/**
 * What an explicit commission recalculation does with the manual commissions
 * (spec 0308, D-2): KEEP_MANUAL refreshes only the rule-driven ones,
 * REGENERATE_ALL rebuilds every commission from the current rules.
 */
enum CommissionRecalculationMode: string
{
    case KeepManual = 'KEEP_MANUAL';
    case RegenerateAll = 'REGENERATE_ALL';
}
