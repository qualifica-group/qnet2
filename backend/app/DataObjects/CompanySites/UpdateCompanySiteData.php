<?php

namespace App\DataObjects\CompanySites;

/**
 * Validated payload for a partial (PATCH) company site update
 * (PUT/PATCH /api/company-sites/{companySite}, spec 0020).
 *
 * Every scalar field is legitimately nullable-or-clearable, so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" — each
 * carries a `*Submitted` flag, mirroring UpdateCompanyData/UpdateProductData.
 * The nested `personal_data` card (contacts + address) is read separately by
 * the controller via the request's toProfile() (ValidatesUserProfile) and
 * handed to CompanySiteService as a ProfileData, mirroring Registry. `banks`
 * present is the AUTHORITATIVE list (add/update/delete diff, BankService::sync);
 * the "preferred bank" now lives on the bank rows themselves (`is_primary`),
 * not on the site. "Altro", the former ERP settings (responsible_*,
 * proforma/invoice progressives, quotation_*) and `is_default` are never
 * accepted here (see CreateCompanySiteData).
 */
final readonly class UpdateCompanySiteData
{
    /**
     * @param  array<int, BankInput>  $banks
     */
    public function __construct(
        public ?string $name = null,
        public ?string $notes = null,
        public bool $notesSubmitted = false,
        public ?int $companyId = null,
        public bool $companyIdSubmitted = false,
        public array $banks = [],
        public bool $banksSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateCompanySiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            notes: array_key_exists('notes', $data) ? $data['notes'] : null,
            notesSubmitted: array_key_exists('notes', $data),
            companyId: self::nullableInt($data, 'company_id'),
            companyIdSubmitted: array_key_exists('company_id', $data),
            banks: array_key_exists('banks', $data) ? self::buildBanks((array) $data['banks']) : [],
            banksSubmitted: array_key_exists('banks', $data),
        );
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `banks` and the nested profile are
     * handled separately by CompanySiteService.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        foreach ([
            'notes' => ['notesSubmitted', 'notes'],
            'company_id' => ['companyIdSubmitted', 'companyId'],
        ] as $column => [$submittedProperty, $valueProperty]) {
            if ($this->{$submittedProperty}) {
                $attributes[$column] = $this->{$valueProperty};
            }
        }

        return $attributes;
    }

    private static function nullableInt(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null ? (int) $data[$key] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $banks
     * @return array<int, BankInput>
     */
    private static function buildBanks(array $banks): array
    {
        return array_values(array_map(
            static fn (array $row): BankInput => new BankInput(
                id: isset($row['id']) ? (int) $row['id'] : null,
                data: new CreateBank(
                    name: (string) ($row['name'] ?? ''),
                    iban: $row['iban'] ?? null,
                    notes: $row['notes'] ?? null,
                    isPrimary: (bool) ($row['is_primary'] ?? false),
                ),
            ),
            $banks,
        ));
    }
}
