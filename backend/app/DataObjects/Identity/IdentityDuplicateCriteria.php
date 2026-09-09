<?php

declare(strict_types=1);

namespace App\DataObjects\Identity;

/**
 * Validated payload for POST /api/identity/duplicate-check: the criteria to
 * match against the EXISTING cards of the shared identity namespace.
 * `contacts` entries stay plain `{type, value}` arrays
 * (CheckIdentityDuplicatesRequest already constrains `type` to
 * email|phone|mobile) rather than typed further — IdentityDuplicateFinder is
 * their only consumer.
 */
final readonly class IdentityDuplicateCriteria
{
    /**
     * @param  array<int, array{type: string, value: string}>  $contacts
     */
    public function __construct(
        public ?string $taxCode,
        public ?string $vatNumber,
        public array $contacts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            taxCode: $data['tax_code'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            contacts: $data['contacts'] ?? [],
        );
    }
}
