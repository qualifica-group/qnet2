<?php

declare(strict_types=1);

namespace App\Imports\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bulk lookups of the payroll imports (specs 0430 D-3/D-4, 0431 D-8): people by
 * tax code and companies by the consultant's code. One query each, whatever
 * the number of rows.
 */
final class PayrollLookups
{
    /** SQL expression comparing `personal_data.tax_code` ignoring case, spaces and tabs. */
    public const string NORMALIZED_TAX_CODE = "UPPER(REPLACE(REPLACE(personal_data.tax_code, ' ', ''), CHAR(9), ''))";

    public static function normalizeTaxCode(string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/u', '', $value));
    }

    /**
     * Normalized tax code => ids of the users holding it (inactive included).
     *
     * @param  array<int, string>  $taxCodes
     * @return array<string, list<int>>
     */
    public function usersByTaxCode(array $taxCodes): array
    {
        if ($taxCodes === []) {
            return [];
        }

        $found = DB::table('personal_data')
            ->join('users', 'users.id', '=', 'personal_data.personable_id')
            ->where('personal_data.personable_type', (new User)->getMorphClass())
            ->whereRaw(self::NORMALIZED_TAX_CODE.' IN ('.implode(',', array_fill(0, count($taxCodes), '?')).')', array_values($taxCodes))
            ->get(['users.id as user_id', DB::raw(self::NORMALIZED_TAX_CODE.' as code')]);

        $map = [];
        foreach ($found as $item) {
            $map[$item->code][] = (int) $item->user_id;
        }

        return $map;
    }

    /**
     * Consultant company code => company id, through the configured VAT numbers.
     *
     * @return array<string, int>
     */
    public function companyIdsByCode(): array
    {
        $vats = array_values(array_map('strval', (array) config('payroll.company_codes')));
        if ($vats === []) {
            return [];
        }

        $idByVat = Company::query()
            ->whereRaw('TRIM(vat_number) IN ('.implode(',', array_fill(0, count($vats), '?')).')', $vats)
            ->orderBy('id')
            ->get(['id', 'vat_number'])
            ->reverse()
            ->mapWithKeys(static fn (Company $company): array => [trim((string) $company->vat_number) => $company->id])
            ->all();

        $map = [];
        foreach ((array) config('payroll.company_codes') as $code => $vat) {
            if (isset($idByVat[(string) $vat])) {
                $map[(string) $code] = $idByVat[(string) $vat];
            }
        }

        return $map;
    }
}
