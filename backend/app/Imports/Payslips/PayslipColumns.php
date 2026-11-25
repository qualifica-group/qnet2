<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Imports\PayrollCosts\PayrollCostColumns;

/**
 * Columns of the labour consultant's net file (spec 0431, D-8, D-9). Headers
 * are matched ignoring case, accents and repeated spaces. The tax code travels
 * in the repeated `RIP.n: TIPO` / `RIP.n: CODICE` pairs (keys `rip.n.type`,
 * `rip.n.code`); every other column of the file is ignored.
 */
final class PayslipColumns
{
    public const string COMPANY_CODE = 'company_code';

    public const string MONTH = 'month';

    public const string YEAR = 'year';

    public const string PAYSLIP_TYPE = 'payslip_type';

    public const string AMOUNT = 'amount';

    public const string EMPLOYEE_CODE = 'employee_code';

    public const string IBAN = 'iban';

    public const string FOREIGN_IBAN = 'foreign_iban';

    /** @var array<string, string> column key => canonical header */
    public const array REQUIRED = [
        self::COMPANY_CODE => 'AZIENDA',
        self::MONTH => 'MESE',
        self::YEAR => 'ANNO',
        self::PAYSLIP_TYPE => 'TIPO CEDOLINO',
        self::AMOUNT => 'IMPORTO',
    ];

    /** @var array<string, string> */
    public const array OPTIONAL = [
        self::EMPLOYEE_CODE => 'CODICE DIPENDENTE',
        self::IBAN => 'CODICE IBAN',
        self::FOREIGN_IBAN => 'CODICE IBAN ESTERO',
    ];

    public const string TAX_CODE_TYPE = 'codicefiscale';

    private const string SPLIT_PATTERN = '/^rip\.\s*(\d+)\s*:\s*(tipo|codice)$/';

    /**
     * Normalized header => column key, for the fixed columns.
     *
     * @return array<string, string>
     */
    public static function lookup(): array
    {
        $lookup = [];
        foreach ([...self::REQUIRED, ...self::OPTIONAL] as $key => $header) {
            $lookup[PayrollCostColumns::normalize($header)] = $key;
        }

        return $lookup;
    }

    /**
     * Column key of a `RIP.n: TIPO|CODICE` header, null for any other header.
     */
    public static function splitKey(string $normalizedHeader): ?string
    {
        if (preg_match(self::SPLIT_PATTERN, $normalizedHeader, $match) !== 1) {
            return null;
        }

        return self::splitColumn((int) $match[1], $match[2] === 'tipo' ? 'type' : 'code');
    }

    public static function splitColumn(int $index, string $part): string
    {
        return "rip.{$index}.{$part}";
    }

    /**
     * Indexes n having both the TIPO and the CODICE column, ascending.
     *
     * @param  array<string, mixed>  $keys  column keys present (as array keys)
     * @return list<int>
     */
    public static function splitIndexes(array $keys): array
    {
        $indexes = [];
        foreach (array_keys($keys) as $key) {
            if (preg_match('/^rip\.(\d+)\.type$/', (string) $key, $match) === 1 && isset($keys[self::splitColumn((int) $match[1], 'code')])) {
                $indexes[] = (int) $match[1];
            }
        }
        sort($indexes);

        return $indexes;
    }
}
