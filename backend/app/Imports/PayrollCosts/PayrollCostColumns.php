<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use Illuminate\Support\Str;

/**
 * Columns of the labour consultant's cost file (spec 0430, D-5). Headers are
 * matched ignoring case, accents and repeated spaces; the other columns of the
 * file (name, hourly average...) are ignored.
 */
final class PayrollCostColumns
{
    public const string COMPANY_CODE = 'company_code';

    public const string TAX_CODE = 'tax_code';

    public const string MONTH = 'month';

    public const string YEAR = 'year';

    public const string SALARY = 'salary_cost';

    public const string CONTRIBUTION = 'contribution_cost';

    public const string INSURANCE = 'insurance_cost';

    public const string HOURS = 'ordinary_hours';

    public const string TOTAL = 'total_cost';

    public const string HIRED_ON = 'hired_on';

    public const string TERMINATED_ON = 'terminated_on';

    /** @var array<string, string> column key => canonical header */
    public const array REQUIRED = [
        self::COMPANY_CODE => 'AZIENDA',
        self::TAX_CODE => 'Codice fiscale dipendente',
        self::MONTH => 'Mese da',
        self::YEAR => 'Anno da',
        self::SALARY => 'Costo retribuzione',
        self::CONTRIBUTION => 'Costo contributivo',
        self::INSURANCE => 'Costo inail',
        self::HOURS => 'Ore ordinarie',
        self::TOTAL => 'TOTALE COSTO MENSILE',
    ];

    /** @var array<string, string> */
    public const array OPTIONAL = [
        self::HIRED_ON => 'Data assunzione',
        self::TERMINATED_ON => 'Data cessazione',
    ];

    /**
     * Normalized header => column key.
     *
     * @return array<string, string>
     */
    public static function lookup(): array
    {
        $lookup = [];
        foreach ([...self::REQUIRED, ...self::OPTIONAL] as $key => $header) {
            $lookup[self::normalize($header)] = $key;
        }

        return $lookup;
    }

    public static function normalize(string $header): string
    {
        return Str::lower((string) preg_replace('/\s+/u', ' ', trim(Str::ascii($header))));
    }
}
