<?php

namespace App\DataObjects\PersonalData;

use App\Enums\PersonalDataTypeEnum;

/**
 * Declared payload for creating/updating a PersonalData card (see
 * standards/architecture.md → Data Transfer Objects). Built at the HTTP
 * boundary by the FormRequest and consumed by PersonalDataService — the service
 * reads typed properties, never a "magic flying array".
 *
 * Both individual and company shapes are represented; the type drives which
 * fields are meaningful (the FormRequest enforces the per-type requirements).
 */
final readonly class CreatePersonalData
{
    public function __construct(
        public PersonalDataTypeEnum $type,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $companyName = null,
        public ?string $taxCode = null,
        public ?string $vatNumber = null,
        public ?string $sdiCode = null,
        public ?string $birthDate = null,
        public ?int $birthCityId = null,
        public ?int $residenceCityId = null,
        public ?string $gender = null,
    ) {}

    /**
     * The user-facing display name derived from this card — the single source of
     * truth for `users.name` on the nested user write (ADR 0012), reused by both
     * create and update so the derivation stays consistent.
     *
     * Company → the trimmed company name; individual → first + last collapsed to a
     * single spaced string. Capped to the `users.name` column width (255).
     */
    public function displayName(): string
    {
        $name = $this->type === PersonalDataTypeEnum::Company
            ? trim((string) $this->companyName)
            : trim((string) preg_replace('/\s+/', ' ', $this->firstName.' '.$this->lastName));

        return mb_substr($name, 0, 255);
    }

    /**
     * The attributes ready for mass assignment on the PersonalData model. The
     * array is forwarded verbatim to Eloquent (allowed framework-array
     * exception), not read field-by-field as business data.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type->value,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company_name' => $this->companyName,
            'tax_code' => $this->taxCode,
            'vat_number' => $this->vatNumber,
            'sdi_code' => $this->sdiCode,
            'birth_date' => $this->birthDate,
            'birth_city_id' => $this->birthCityId,
            'residence_city_id' => $this->residenceCityId,
            'gender' => $this->gender,
        ];
    }
}
