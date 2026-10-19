<?php

namespace App\DataObjects\BusinessFunctions;

/**
 * Validated payload for creating a business function (POST /api/business-functions).
 *
 * Declared DTO (no "magic flying array") so the StoreBusinessFunctionRequest →
 * BusinessFunctionService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `type` is the single client-facing mutually-exclusive selector; the Service
 * maps it onto the two `is_business_unit`/`is_business_service` columns.
 * `users`/`operationalSites` are null when the client did not submit the key
 * at all (nothing to sync — a brand new function starts with neither).
 */
final readonly class CreateBusinessFunctionData
{
    /**
     * @param  array<int, int>|null  $users
     * @param  array<int, int>|null  $operationalSites
     * @param  array<int, int>|null  $companyIds
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?int $managerId = null,
        public ?int $parentId = null,
        public ?array $users = null,
        public ?array $operationalSites = null,
        public ?array $companyIds = null,
        /** Spec 0280: a plain per-node flag, defaulting to active when omitted. */
        public bool $isActive = true,
    ) {}

    /**
     * Build from the validated StoreBusinessFunctionRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            type: array_key_exists('type', $data) ? $data['type'] : null,
            managerId: array_key_exists('manager_id', $data) && $data['manager_id'] !== null
                ? (int) $data['manager_id']
                : null,
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null
                ? (int) $data['parent_id']
                : null,
            users: array_key_exists('users', $data) ? array_map('intval', (array) $data['users']) : null,
            operationalSites: array_key_exists('operational_sites', $data)
                ? array_map('intval', (array) $data['operational_sites'])
                : null,
            companyIds: array_key_exists('company_ids', $data) && $data['company_ids'] !== null
                ? array_map('intval', (array) $data['company_ids'])
                : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    public function hasUsers(): bool
    {
        return $this->users !== null;
    }

    public function hasOperationalSites(): bool
    {
        return $this->operationalSites !== null;
    }

    public function hasCompanies(): bool
    {
        return $this->companyIds !== null;
    }
}
