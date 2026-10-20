<?php

namespace App\Tables\Support;

use App\Models\OperationalSite;
use Illuminate\Support\Facades\DB;

/**
 * The operational site label as one SQL expression, for the grids that read it
 * through a join on `operational_sites` (the installment schedules): the alias
 * when set, otherwise the primary address `line1` (the site's identity, same
 * convention as OperationalSiteColumn), the id only when the site has neither.
 * One expression drives cell, sort, Set Filter, distinct values and groups,
 * so they cannot diverge.
 */
final class OperationalSiteLabelSql
{
    public static function expression(): string
    {
        // The morph alias is a code constant, quoted by the driver: never request input.
        $type = DB::getPdo()->quote((new OperationalSite)->getMorphClass());

        return 'COALESCE(operational_sites.alias, (SELECT addresses.line1 FROM addresses'
            .' WHERE addresses.addressable_type = '.$type.' AND addresses.addressable_id = operational_sites.id'
            .' AND addresses.line1 IS NOT NULL ORDER BY addresses.is_primary DESC, addresses.id LIMIT 1),'
            .' CAST(operational_sites.id AS CHAR))';
    }
}
