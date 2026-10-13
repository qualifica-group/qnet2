<?php

namespace App\Migrations\Sources\Concerns;

use App\Migrations\AbstractMigrationSource;
use App\Models\VatRate;
use RuntimeException;

/**
 * Field-mapping helpers shared by the migration sources that create products:
 * ProductsSource (legacy `services`) and CostProductsSource (the legacy cost
 * tables, spec 0174). Both carry the same optional VAT rate, the same
 * unmappable supplier and the same free-text cleanup.
 *
 * @phpstan-require-extends AbstractMigrationSource
 */
trait MapsExternalProductRecord
{
    /** Length of the `products.name` column. */
    private const int NAME_MAX_LENGTH = 191;

    /**
     * The required product name, trimmed and cut to the column: the "[OLD] "
     * prefix the legacy adds can push a long title past it.
     */
    private function productName(mixed $externalName): string
    {
        $name = trim((string) $externalName);

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        return mb_substr($name, 0, self::NAME_MAX_LENGTH);
    }

    /**
     * Remap the OPTIONAL external VAT rate reference to the qnet vat_rate id via
     * `old_id`. Absent/blank → null (no warning: the legacy row simply carries
     * none); a reference that resolves to no migrated rate → null plus a
     * non-fatal warning, never a failed row — a product is perfectly valid
     * without a VAT rate, and failing here would block the whole catalogue on a
     * missing lookup.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveVatRate(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId(VatRate::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved vat_rate_id (external id {$externalRef}); left empty, migrate vat-rates first.";
        }

        return $id;
    }

    /**
     * `supplier_id` cannot be remapped (`registries` carries no `old_id` and has
     * no migration source). The external reference is dropped to null; a
     * non-fatal warning records that the link was not carried.
     *
     * @param  array<int, string>  $warnings
     */
    private function unresolvableReference(string $field, mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $warnings[] = "{$field} (external id {$externalRef}) not remapped; the reference has no migration source and was left empty.";

        return null;
    }

    /**
     * A blank external text (description, code, ...) becomes null; otherwise
     * the trimmed string is kept.
     */
    private function trimmedText(mixed $externalText): ?string
    {
        if ($externalText === null) {
            return null;
        }

        $text = trim((string) $externalText);

        return $text !== '' ? $text : null;
    }
}
