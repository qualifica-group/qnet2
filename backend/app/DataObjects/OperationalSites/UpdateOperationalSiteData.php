<?php

namespace App\DataObjects\OperationalSites;

/**
 * Validated payload for a partial (PATCH) operational site update
 * (PUT/PATCH /api/operational-sites/{operationalSite}, spec 0011).
 *
 * Declared DTO (no "magic flying array") so the UpdateOperationalSiteRequest →
 * OperationalSiteService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * Every field here is a legitimately nullable VALUE (clearing postal_code,
 * resetting a geo FK), so a plain null property cannot distinguish "not
 * submitted" from "submitted as null" — the `*Submitted` flags carry that
 * distinction (mirroring UpdateCompanyData's `vatNumberSubmitted`). Unlike
 * UpdateCompanyData's nested (all-or-nothing) `address`, each field here is
 * independently `sometimes` (AC-011): the Service merges only the SUBMITTED
 * fields onto the site's current address, leaving the rest untouched.
 */
final readonly class UpdateOperationalSiteData
{
    public function __construct(
        public ?string $line1 = null,
        public bool $line1Submitted = false,
        public ?string $postalCode = null,
        public bool $postalCodeSubmitted = false,
        public ?int $countryId = null,
        public bool $countryIdSubmitted = false,
        public ?int $stateId = null,
        public bool $stateIdSubmitted = false,
        public ?int $provinceId = null,
        public bool $provinceIdSubmitted = false,
        public ?int $cityId = null,
        public bool $cityIdSubmitted = false,
        public ?string $alias = null,
        public bool $aliasSubmitted = false,
        public ?bool $isActive = null,
    ) {}

    /**
     * Build from the validated UpdateOperationalSiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            line1: array_key_exists('line1', $data) ? (string) $data['line1'] : null,
            line1Submitted: array_key_exists('line1', $data),
            postalCode: array_key_exists('postal_code', $data) ? $data['postal_code'] : null,
            postalCodeSubmitted: array_key_exists('postal_code', $data),
            countryId: isset($data['country_id']) ? (int) $data['country_id'] : null,
            countryIdSubmitted: array_key_exists('country_id', $data),
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            stateIdSubmitted: array_key_exists('state_id', $data),
            provinceId: isset($data['province_id']) ? (int) $data['province_id'] : null,
            provinceIdSubmitted: array_key_exists('province_id', $data),
            cityId: isset($data['city_id']) ? (int) $data['city_id'] : null,
            cityIdSubmitted: array_key_exists('city_id', $data),
            alias: array_key_exists('alias', $data) ? $data['alias'] : null,
            aliasSubmitted: array_key_exists('alias', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * Whether ANY address-related field was submitted at all — no-op update
     * (site has no own writable column) when false.
     */
    public function hasAddressChanges(): bool
    {
        return $this->line1Submitted || $this->postalCodeSubmitted
            || $this->countryIdSubmitted || $this->stateIdSubmitted
            || $this->provinceIdSubmitted || $this->cityIdSubmitted;
    }
}
