<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

/**
 * A row of the net file that passed the cell checks (spec 0431, D-8).
 */
final readonly class ParsedPayslipRow
{
    public function __construct(
        public int $number,
        public string $companyCode,
        public string $taxCode,
        public string $period,
        public string $payslipType,
        public int $cents,
        public ?string $employeeCode,
        public ?string $iban,
    ) {}
}
