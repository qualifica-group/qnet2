<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PersonalData;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Counterparty label of the accounting lists: "{name} - {fiscal id}", where the
 * fiscal id is the registry's VAT number or, when it has none, its tax code
 * (user request 2026-11-12). Just the name when the registry has neither.
 *
 * Lists read the fiscal id through a correlated subquery on the registry's
 * card (one query, no extra rows, same path for join-built and eager-loaded
 * lists); detail resources read it from the eager-loaded card.
 */
final class RegistryFiscalLabel
{
    private const string SEPARATOR = ' - ';

    /**
     * Subquery returning the fiscal id of the registry whose id is in
     * `$registryIdColumn` (VAT number first, then tax code; blanks skipped).
     *
     * @return Builder<PersonalData>
     */
    public static function fiscalIdSubquery(string $registryIdColumn): Builder
    {
        return PersonalData::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(vat_number), ''), NULLIF(TRIM(tax_code), ''))")
            ->where('personable_type', (new Registry)->getMorphClass())
            ->whereColumn('personable_id', $registryIdColumn)
            ->limit(1);
    }

    /** Label of a registry whose card is already loaded (`personalData`): this never queries. */
    public static function forRegistry(Registry $registry): string
    {
        $card = $registry->personalData;
        $fiscalId = self::filled($card?->vat_number) ?? self::filled($card?->tax_code);

        return (string) self::compose($registry->name, $fiscalId);
    }

    public static function compose(?string $name, ?string $fiscalId): ?string
    {
        if ($name === null || $fiscalId === null || $fiscalId === '') {
            return $name;
        }

        return $name.self::SEPARATOR.$fiscalId;
    }

    private static function filled(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
