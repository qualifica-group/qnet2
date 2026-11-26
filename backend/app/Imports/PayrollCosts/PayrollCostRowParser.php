<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use App\Imports\ChartOfAccounts\ImportRow;
use App\Imports\Support\ImportNumber;
use App\Imports\Support\PayrollLookups;
use DateTimeImmutable;

/**
 * Checks the cells of one row (spec 0430, D-6): required values, amounts
 * ("1.234,56", "1234,56", "1234.56", negatives allowed), dd/mm/yyyy dates, a
 * valid month and a total equal to salary + contributions + insurance.
 */
final class PayrollCostRowParser
{
    private const int TOTAL_TOLERANCE_CENTS = 1;

    private const int MIN_YEAR = 1990;

    private const int MAX_YEAR = 2100;

    public static function normalizeTaxCode(string $value): string
    {
        return PayrollLookups::normalizeTaxCode($value);
    }

    /**
     * Null when at least one cell is invalid (the errors are recorded).
     */
    public function parse(ImportRow $row, PayrollCostImportErrors $errors): ?ParsedPayrollRow
    {
        $before = $errors->count();
        $companyCode = $this->required($row, PayrollCostColumns::COMPANY_CODE, $errors);
        $taxCode = self::normalizeTaxCode($this->required($row, PayrollCostColumns::TAX_CODE, $errors));
        $period = $this->period($row, $errors);
        $salary = $this->cents($row, PayrollCostColumns::SALARY, $errors);
        $contribution = $this->cents($row, PayrollCostColumns::CONTRIBUTION, $errors);
        $insurance = $this->cents($row, PayrollCostColumns::INSURANCE, $errors);
        $total = $this->cents($row, PayrollCostColumns::TOTAL, $errors);
        $hours = $this->amount($row, PayrollCostColumns::HOURS, $errors);
        $hiredOn = $this->date($row, PayrollCostColumns::HIRED_ON, $errors);
        $terminatedOn = $this->date($row, PayrollCostColumns::TERMINATED_ON, $errors);

        if ($errors->count() === $before && abs($total - ($salary + $contribution + $insurance)) > self::TOTAL_TOLERANCE_CENTS) {
            $errors->add($row->number, PayrollCostColumns::TOTAL, 'total_mismatch');
        }
        if ($errors->count() !== $before) {
            return null;
        }

        return new ParsedPayrollRow(
            $row->number, $companyCode, $taxCode, (string) $period, $salary, $contribution, $insurance, $total,
            round($hours, 2), $hiredOn, $terminatedOn,
        );
    }

    private function required(ImportRow $row, string $key, PayrollCostImportErrors $errors): string
    {
        $value = $row->cells[$key] ?? '';
        if ($value === '') {
            $errors->add($row->number, $key, 'required');
        }

        return $value;
    }

    private function period(ImportRow $row, PayrollCostImportErrors $errors): ?string
    {
        $month = $this->required($row, PayrollCostColumns::MONTH, $errors);
        $year = $this->required($row, PayrollCostColumns::YEAR, $errors);
        if ($month === '' || $year === '') {
            return null;
        }

        $valid = true;
        if (! ctype_digit($month) || (int) $month < 1 || (int) $month > 12) {
            $errors->add($row->number, PayrollCostColumns::MONTH, 'invalid_month');
            $valid = false;
        }
        if (! ctype_digit($year) || (int) $year < self::MIN_YEAR || (int) $year > self::MAX_YEAR) {
            $errors->add($row->number, PayrollCostColumns::YEAR, 'invalid_year');
            $valid = false;
        }

        return $valid ? sprintf('%04d-%02d', (int) $year, (int) $month) : null;
    }

    private function cents(ImportRow $row, string $key, PayrollCostImportErrors $errors): int
    {
        return (int) round($this->amount($row, $key, $errors) * 100);
    }

    private function amount(ImportRow $row, string $key, PayrollCostImportErrors $errors): float
    {
        $value = $this->required($row, $key, $errors);
        if ($value === '') {
            return 0.0;
        }

        $number = ImportNumber::parse($value);
        if ($number === null) {
            $errors->add($row->number, $key, 'invalid_amount');

            return 0.0;
        }

        return $number;
    }

    private function date(ImportRow $row, string $key, PayrollCostImportErrors $errors): ?string
    {
        $value = $row->cells[$key] ?? '';
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        if ($date === false || $date->format('d/m/Y') !== $this->padDate($value)) {
            $errors->add($row->number, $key, 'invalid_date');

            return null;
        }

        return $date->format('Y-m-d');
    }

    private function padDate(string $value): string
    {
        return (string) preg_replace_callback('/^(\d{1,2})\/(\d{1,2})\//', static fn (array $m): string => sprintf('%02d/%02d/', $m[1], $m[2]), $value);
    }
}
