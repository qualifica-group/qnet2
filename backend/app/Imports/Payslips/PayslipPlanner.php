<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Imports\ChartOfAccounts\ImportRow;
use App\Imports\Support\PayrollLookups;
use App\Models\PayrollCost;
use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * Validates every row of the net file and decides what would be written (spec
 * 0431, D-3, D-4, D-8, D-10, D-12): one period per file, a person per tax code,
 * a company per consultant code, one payslip per company, person and type, no
 * update of a paid payslip. Lookups are resolved in bulk, so the query count
 * does not depend on the row count.
 */
final class PayslipPlanner
{
    public function __construct(
        private readonly PayslipRowParser $parser,
        private readonly PayrollLookups $lookups,
        private readonly PayslipSupplierResolver $suppliers,
        private readonly PayslipIbanPlanner $ibans,
    ) {}

    /**
     * @param  list<ImportRow>  $rows
     * @param  array<string, string>  $headers  column key => header in the file
     */
    public function plan(array $rows, array $headers): PayslipPlan
    {
        $issues = new PayslipIssues($headers);

        // Step 1: cell-level validation
        $parsed = [];
        foreach ($rows as $row) {
            $item = $this->parser->parse($row, $issues);
            if ($item !== null) {
                $parsed[] = $item;
            }
        }

        // Step 2: one period for the whole file
        $period = $this->checkPeriod($parsed, $issues);

        // Step 3: people, companies and payslips already written, then the cross-row checks
        $entries = $this->buildEntries(
            $parsed,
            $period,
            $this->lookups->usersByTaxCode(array_values(array_unique(array_map(static fn (ParsedPayslipRow $row): string => $row->taxCode, $parsed)))),
            $this->lookups->companyIdsByCode(),
            $period === null ? collect() : $this->existing($period),
            $issues,
        );

        // Step 4: supplier registries, IBANs and the cost-file cross-check of the valid rows
        $suppliers = $this->suppliers->resolve(array_column(array_map(static fn (PayslipEntry $entry): array => [$entry->userId, $entry->taxCode], $entries), 1, 0));
        $this->warnDuplicateSuppliers($entries, $suppliers, $issues);
        $this->warnMissingCosts($entries, $period, $issues);

        return new PayslipPlan($entries, $issues, $period, count($rows), $suppliers, $this->ibans->plan($entries, $issues));
    }

    /**
     * The period of the first valid row; later rows with another month are
     * errors. Null when the file has no valid row or no single month.
     *
     * @param  list<ParsedPayslipRow>  $parsed
     */
    private function checkPeriod(array $parsed, PayslipIssues $issues): ?string
    {
        if ($parsed === []) {
            return null;
        }

        $reference = $parsed[0]->period;
        $unique = true;
        foreach ($parsed as $row) {
            if ($row->period !== $reference) {
                $issues->error($row->number, PayslipColumns::MONTH, 'other_period', ['period' => $reference]);
                $unique = false;
            }
        }

        return $unique ? $reference : null;
    }

    /**
     * @param  list<ParsedPayslipRow>  $parsed
     * @param  array<string, list<int>>  $usersByTaxCode
     * @param  array<string, int>  $companyByCode
     * @param  Collection<string, Payslip>  $existing
     * @return list<PayslipEntry>
     */
    private function buildEntries(array $parsed, ?string $period, array $usersByTaxCode, array $companyByCode, Collection $existing, PayslipIssues $issues): array
    {
        $entries = [];
        $seen = [];

        foreach ($parsed as $row) {
            $userIds = $usersByTaxCode[$row->taxCode] ?? [];
            $companyId = $companyByCode[$row->companyCode] ?? null;
            $before = $issues->errorCount();

            if (count($userIds) !== 1) {
                $issues->error($row->number, null, $userIds === [] ? 'tax_code_not_found' : 'tax_code_many_users');
            }
            if ($companyId === null) {
                $issues->error($row->number, PayslipColumns::COMPANY_CODE, 'company_unmapped');
            }

            $current = null;
            if ($companyId !== null && count($userIds) === 1) {
                $key = PayslipEntry::key($companyId, $userIds[0], $row->payslipType);
                if (isset($seen[$key])) {
                    $issues->error($row->number, PayslipColumns::PAYSLIP_TYPE, 'duplicate');
                }
                $seen[$key] = true;

                $current = $existing->get($key);
                if ($current !== null && $current->purchaseInvoice->hasPayments()) {
                    $issues->error($row->number, null, 'already_paid');
                }
            }

            if ($issues->errorCount() === $before && $period !== null && $row->period === $period) {
                $entries[] = new PayslipEntry($row->number, $companyId, $userIds[0], $row->taxCode, $row->period, $row->payslipType, $row->employeeCode, $row->cents, $row->iban, $current);
            }
        }

        return $entries;
    }

    /**
     * Payslips of the period keyed by company, person and type, with their schedule.
     *
     * @return Collection<string, Payslip>
     */
    private function existing(string $period): Collection
    {
        return Payslip::query()
            ->with('purchaseInvoice.installments')
            ->where('period', $period)
            ->get()
            ->keyBy(static fn (Payslip $payslip): string => PayslipEntry::key($payslip->company_id, $payslip->user_id, $payslip->payslip_type));
    }

    /**
     * @param  list<PayslipEntry>  $entries
     * @param  array<int, array{action: string, registry_id: int|null, duplicate: bool}>  $suppliers
     */
    private function warnDuplicateSuppliers(array $entries, array $suppliers, PayslipIssues $issues): void
    {
        $warned = [];
        foreach ($entries as $entry) {
            if (($suppliers[$entry->userId]['duplicate'] ?? false) && ! isset($warned[$entry->userId])) {
                $warned[$entry->userId] = true;
                $issues->warning($entry->number, null, 'supplier_many_matches');
            }
        }
    }

    /**
     * @param  list<PayslipEntry>  $entries
     */
    private function warnMissingCosts(array $entries, ?string $period, PayslipIssues $issues): void
    {
        if ($entries === [] || $period === null) {
            return;
        }

        $costs = PayrollCost::query()->where('period', $period)->get(['company_id', 'user_id'])
            ->mapWithKeys(static fn (PayrollCost $cost): array => [$cost->company_id.'|'.$cost->user_id => true]);

        foreach ($entries as $entry) {
            if (! $costs->has($entry->companyId.'|'.$entry->userId)) {
                $issues->warning($entry->number, null, 'no_cost_row');
            }
        }
    }
}
