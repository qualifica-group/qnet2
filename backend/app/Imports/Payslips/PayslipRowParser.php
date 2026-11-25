<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Imports\ChartOfAccounts\ImportRow;
use App\Imports\Support\ImportNumber;
use App\Imports\Support\PayrollLookups;

/**
 * Checks the cells of one row of the net file (spec 0431, D-8, D-9): required
 * values, a valid month, a positive amount, the tax code taken from the first
 * `Codice fiscale` ripartition, the IBAN (foreign one when the first is empty).
 */
final class PayslipRowParser
{
    private const int MIN_YEAR = 1990;

    private const int MAX_YEAR = 2100;

    private const int MAX_TYPE_LENGTH = 50;

    private const int MAX_EMPLOYEE_CODE_LENGTH = 20;

    /**
     * Null when at least one cell is invalid (the errors are recorded).
     */
    public function parse(ImportRow $row, PayslipIssues $issues): ?ParsedPayslipRow
    {
        $before = $issues->errorCount();
        $companyCode = $this->required($row, PayslipColumns::COMPANY_CODE, $issues);
        $period = $this->period($row, $issues);
        $type = $this->required($row, PayslipColumns::PAYSLIP_TYPE, $issues);
        $cents = $this->cents($row, $issues);
        $taxCode = $this->taxCode($row, $issues);
        $employeeCode = $row->cells[PayslipColumns::EMPLOYEE_CODE] ?? '';

        if (mb_strlen($type) > self::MAX_TYPE_LENGTH) {
            $issues->error($row->number, PayslipColumns::PAYSLIP_TYPE, 'too_long', ['max' => self::MAX_TYPE_LENGTH]);
        }
        if (mb_strlen($employeeCode) > self::MAX_EMPLOYEE_CODE_LENGTH) {
            $issues->error($row->number, PayslipColumns::EMPLOYEE_CODE, 'too_long', ['max' => self::MAX_EMPLOYEE_CODE_LENGTH]);
        }
        if ($issues->errorCount() !== $before) {
            return null;
        }

        return new ParsedPayslipRow($row->number, $companyCode, $taxCode, (string) $period, $type, $cents, $employeeCode === '' ? null : $employeeCode, $this->iban($row));
    }

    private function required(ImportRow $row, string $key, PayslipIssues $issues): string
    {
        $value = $row->cells[$key] ?? '';
        if ($value === '') {
            $issues->error($row->number, $key, 'required');
        }

        return $value;
    }

    private function period(ImportRow $row, PayslipIssues $issues): ?string
    {
        $month = $this->required($row, PayslipColumns::MONTH, $issues);
        $year = $this->required($row, PayslipColumns::YEAR, $issues);
        if ($month === '' || $year === '') {
            return null;
        }

        $valid = true;
        if (! ctype_digit($month) || (int) $month < 1 || (int) $month > 12) {
            $issues->error($row->number, PayslipColumns::MONTH, 'invalid_month');
            $valid = false;
        }
        if (! ctype_digit($year) || (int) $year < self::MIN_YEAR || (int) $year > self::MAX_YEAR) {
            $issues->error($row->number, PayslipColumns::YEAR, 'invalid_year');
            $valid = false;
        }

        return $valid ? sprintf('%04d-%02d', (int) $year, (int) $month) : null;
    }

    private function cents(ImportRow $row, PayslipIssues $issues): int
    {
        $value = $this->required($row, PayslipColumns::AMOUNT, $issues);
        if ($value === '') {
            return 0;
        }

        $number = ImportNumber::parse($value);
        if ($number === null) {
            $issues->error($row->number, PayslipColumns::AMOUNT, 'invalid_amount');

            return 0;
        }

        $cents = (int) round($number * 100);
        if ($cents <= 0) {
            $issues->error($row->number, PayslipColumns::AMOUNT, 'non_positive_amount');
        }

        return $cents;
    }

    /**
     * The CODICE of the first ripartition whose TIPO is "Codice fiscale".
     */
    private function taxCode(ImportRow $row, PayslipIssues $issues): string
    {
        foreach (PayslipColumns::splitIndexes($row->cells) as $index) {
            $type = mb_strtolower((string) preg_replace('/\s+/u', '', $row->cells[PayslipColumns::splitColumn($index, 'type')]));
            if ($type !== PayslipColumns::TAX_CODE_TYPE) {
                continue;
            }

            $codeKey = PayslipColumns::splitColumn($index, 'code');
            $code = PayrollLookups::normalizeTaxCode($row->cells[$codeKey]);
            if ($code === '') {
                $issues->error($row->number, $codeKey, 'required');
            }

            return $code;
        }

        $issues->error($row->number, null, 'tax_code_missing');

        return '';
    }

    private function iban(ImportRow $row): ?string
    {
        $iban = $row->cells[PayslipColumns::IBAN] ?? '';
        if ($iban === '') {
            $iban = $row->cells[PayslipColumns::FOREIGN_IBAN] ?? '';
        }
        $iban = strtoupper((string) preg_replace('/\s+/', '', $iban));

        return $iban === '' ? null : $iban;
    }
}
