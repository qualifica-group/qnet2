<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Models\Payslip;

/**
 * A validated row of the net file ready to be written: a new payslip, or the
 * existing one (same company, person, month and type) to update.
 */
final readonly class PayslipEntry
{
    public function __construct(
        public int $number,
        public int $companyId,
        public int $userId,
        public string $taxCode,
        public string $period,
        public string $payslipType,
        public ?string $employeeCode,
        public int $cents,
        public ?string $iban,
        public ?Payslip $existing,
    ) {}

    public function net(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    /** Unique key of a payslip (D-2). */
    public static function key(int $companyId, int $userId, string $payslipType): string
    {
        return $companyId.'|'.$userId.'|'.$payslipType;
    }
}
