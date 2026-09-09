<?php

declare(strict_types=1);

namespace App\DataObjects\Identity;

/**
 * A single EXISTING holder of the criteria checked by
 * IdentityDuplicateFinder: its morph alias + id, display name and every
 * channel that matches, cumulative. Intentionally carries no contact value,
 * tax code or VAT number — the response must never leak another record's PII.
 */
final readonly class IdentityDuplicateMatch
{
    /**
     * @param  string  $ownerType  morph alias of the holder ("user"|"registry"|"referent")
     * @param  array<int, string>  $matchedOn  subset of ["email","phone","mobile","tax_code","vat_number"], in that order
     */
    public function __construct(
        public string $ownerType,
        public int $ownerId,
        public string $name,
        public array $matchedOn,
    ) {}
}
