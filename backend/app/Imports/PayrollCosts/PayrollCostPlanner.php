<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use App\Imports\ChartOfAccounts\ImportRow;
use App\Models\Company;
use App\Models\PayrollCost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validates every row of the cost file and decides what would be written
 * (spec 0430, D-1..D-4): one period per file, a person per tax code, a
 * company per consultant code, no repeated company + person. Lookups are
 * resolved in bulk, so the query count does not depend on the row count.
 */
final class PayrollCostPlanner
{
    public function __construct(private readonly PayrollCostRowParser $parser) {}

    /**
     * @param  list<ImportRow>  $rows
     * @param  array<string, string>  $headers  column key => header in the file
     */
    public function plan(array $rows, array $headers, User $actor): PayrollCostPlan
    {
        $errors = new PayrollCostImportErrors($headers);

        // Step 1: cell-level validation
        $parsed = [];
        foreach ($rows as $row) {
            $item = $this->parser->parse($row, $errors);
            if ($item !== null) {
                $parsed[] = $item;
            }
        }

        // Step 2: one period for the whole file
        $period = $this->checkPeriod($parsed, $errors);

        // Step 3: people and companies, then the cross-row checks
        $usersByTaxCode = $this->usersByTaxCode(array_unique(array_map(static fn (ParsedPayrollRow $r): string => $r->taxCode, $parsed)));
        $companyByCode = $this->companyIdsByCode();
        $entries = $this->buildEntries($parsed, $usersByTaxCode, $companyByCode, $period, $actor, $errors);

        return new PayrollCostPlan($entries, $errors, $period, count($rows), $period === null ? 0 : PayrollCost::query()->where('period', $period)->count());
    }

    /**
     * The period of the first valid row; later rows with another month are
     * errors. Null when the file has no valid row or no single month.
     *
     * @param  list<ParsedPayrollRow>  $parsed
     */
    private function checkPeriod(array $parsed, PayrollCostImportErrors $errors): ?string
    {
        if ($parsed === []) {
            return null;
        }

        $reference = $parsed[0]->period;
        $unique = true;
        foreach ($parsed as $row) {
            if ($row->period !== $reference) {
                $errors->add($row->number, PayrollCostColumns::MONTH, 'other_period', ['period' => $reference]);
                $unique = false;
            }
        }

        return $unique ? $reference : null;
    }

    /**
     * @param  list<ParsedPayrollRow>  $parsed
     * @param  array<string, list<int>>  $usersByTaxCode
     * @param  array<string, int>  $companyByCode
     * @return list<array<string, mixed>>
     */
    private function buildEntries(array $parsed, array $usersByTaxCode, array $companyByCode, ?string $period, User $actor, PayrollCostImportErrors $errors): array
    {
        $entries = [];
        $seen = [];

        foreach ($parsed as $row) {
            $userIds = $usersByTaxCode[$row->taxCode] ?? [];
            $companyId = $companyByCode[$row->companyCode] ?? null;
            $before = $errors->count();

            if (count($userIds) !== 1) {
                $errors->add($row->number, PayrollCostColumns::TAX_CODE, $userIds === [] ? 'tax_code_not_found' : 'tax_code_many_users');
            }
            if ($companyId === null) {
                $errors->add($row->number, PayrollCostColumns::COMPANY_CODE, 'company_unmapped');
            }
            if ($companyId !== null && isset($seen[$companyId.'|'.$row->taxCode])) {
                $errors->add($row->number, PayrollCostColumns::TAX_CODE, 'duplicate');
            }
            if ($companyId !== null) {
                $seen[$companyId.'|'.$row->taxCode] = true;
            }

            if ($errors->count() === $before && $period !== null && $row->period === $period) {
                $entries[] = $this->entry($row, $companyId, $userIds[0], $actor);
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ParsedPayrollRow $row, int $companyId, int $userId, User $actor): array
    {
        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'period' => $row->period,
            'tax_code' => $row->taxCode,
            'salary_cost' => $this->decimal($row->salaryCents),
            'contribution_cost' => $this->decimal($row->contributionCents),
            'insurance_cost' => $this->decimal($row->insuranceCents),
            'total_cost' => $this->decimal($row->totalCents),
            'ordinary_hours' => number_format($row->ordinaryHours, 2, '.', ''),
            'hired_on' => $row->hiredOn,
            'terminated_on' => $row->terminatedOn,
            'imported_by' => $actor->id,
        ];
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Normalized tax code => ids of the users holding it (inactive included).
     *
     * @param  array<int, string>  $taxCodes
     * @return array<string, list<int>>
     */
    private function usersByTaxCode(array $taxCodes): array
    {
        if ($taxCodes === []) {
            return [];
        }

        $normalized = "UPPER(REPLACE(REPLACE(personal_data.tax_code, ' ', ''), CHAR(9), ''))";
        $found = DB::table('personal_data')
            ->join('users', 'users.id', '=', 'personal_data.personable_id')
            ->where('personal_data.personable_type', (new User)->getMorphClass())
            ->whereRaw($normalized.' IN ('.implode(',', array_fill(0, count($taxCodes), '?')).')', array_values($taxCodes))
            ->get(['users.id as user_id', DB::raw($normalized.' as code')]);

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
    private function companyIdsByCode(): array
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
