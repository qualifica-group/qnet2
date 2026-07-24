<?php

namespace App\DataObjects\CompanySites;

use Illuminate\Http\UploadedFile;

/**
 * Validated payload for creating a company site (POST /api/company-sites,
 * spec 0020). Declared DTO (no "magic flying array") so the
 * StoreCompanySiteRequest → CompanySiteService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * Only the site's OWN scalar fields (name/notes/company_id) plus the banks
 * are carried here. The nested `personal_data` card (contacts + address) is
 * read separately by the controller via the request's toProfile()
 * (ValidatesUserProfile) and handed to CompanySiteService as a ProfileData,
 * mirroring Registry. The "Altro" section, the former ERP settings
 * (responsible_*, proforma/invoice progressives, quotation_*) and
 * `is_default` are never accepted on this path (Altro/ERP settings are now
 * custom fields; the default flag is set exclusively via
 * POST /company-sites/{id}/set-default).
 */
final readonly class CreateCompanySiteData
{
    /**
     * @param  array<int, BankInput>  $banks
     */
    public function __construct(
        public string $name,
        public ?string $notes = null,
        public ?int $companyId = null,
        public array $banks = [],
        public ?UploadedFile $logo = null,
    ) {}

    /**
     * Build from the validated StoreCompanySiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, ?UploadedFile $logo = null): self
    {
        return new self(
            name: (string) $data['name'],
            notes: $data['notes'] ?? null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            banks: self::buildBanks($data['banks'] ?? []),
            logo: $logo,
        );
    }

    public function hasLogo(): bool
    {
        return $this->logo !== null;
    }

    /**
     * The site attributes for a mass-assignment create.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'notes' => $this->notes,
            'company_id' => $this->companyId,
        ];
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
