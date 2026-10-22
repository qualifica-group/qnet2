<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Number of a legacy document unique per (company, year) (specs 0296 D-2,
 * 0301 D-6): the legacy number when that company and year do not have it yet;
 * otherwise, or without a legacy number, the first number past the highest
 * legacy one of the year, the highest already in qnet-2 and the sequence. The
 * sequence never falls behind an imported number, so a native document
 * created later never takes one.
 */
final class LegacyDocumentNumberer
{
    /**
     * @param  class-string<Model>  $documentClass  with `company_id`, `year`, `number`
     * @param  class-string<Model>  $sequenceClass  with `company_id`, `year`, `last_number`
     * @return array{number: int, reassigned: bool}
     */
    public function number(string $documentClass, string $sequenceClass, int $companyId, int $year, ?int $legacyNumber, int $yearMaxNumber): array
    {
        $taken = $documentClass::query()->where('company_id', $companyId)->where('year', $year);
        $sequence = $sequenceClass::query()->firstOrCreate(['company_id' => $companyId, 'year' => $year], ['last_number' => 0]);
        $reassigned = $legacyNumber === null || $legacyNumber < 1 || (clone $taken)->where('number', $legacyNumber)->exists();
        $number = $reassigned
            ? max($yearMaxNumber, (int) (clone $taken)->max('number'), (int) $sequence->last_number) + 1
            : $legacyNumber;

        if ($sequence->last_number < $number) {
            $sequence->update(['last_number' => $number]);
        }

        return ['number' => $number, 'reassigned' => $reassigned];
    }
}
