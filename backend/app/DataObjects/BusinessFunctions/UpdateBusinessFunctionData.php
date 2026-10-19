<?php

namespace App\DataObjects\BusinessFunctions;

/**
 * Validated payload for a partial (PATCH) business function update
 * (PUT/PATCH /api/business-functions/{businessFunction}).
 *
 * Declared DTO (no "magic flying array") so the UpdateBusinessFunctionRequest →
 * BusinessFunctionService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `type`, `manager_id` and `parent_id` are all legitimately nullable VALUES
 * (clearing the type, removing the manager, detaching the parent), so a
 * plain null property cannot distinguish "not submitted" from "submitted as
 * null" the way UpdateUserData/UpdateRoleData do for their non-nullable
 * fields. The `*Submitted` flags carry that distinction explicitly;
 * `hasType()`/`hasManager()`/`hasParentId()`/`hasUsers()`/
 * `hasOperationalSites()` expose it.
 */
final readonly class UpdateBusinessFunctionData
{
    /**
     * @param  array<int, int>|null  $users
     * @param  array<int, int>|null  $operationalSites
     * @param  array<int, int>|null  $companyIds
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public bool $typeSubmitted = false,
        public ?int $managerId = null,
        public bool $managerSubmitted = false,
        public ?int $parentId = null,
        public bool $parentIdSubmitted = false,
        public ?array $users = null,
        public ?array $operationalSites = null,
        public ?array $companyIds = null,
        public ?bool $isActive = null,
    ) {}

    /**
     * Build from the validated UpdateBusinessFunctionRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            type: array_key_exists('type', $data) ? $data['type'] : null,
            typeSubmitted: array_key_exists('type', $data),
            managerId: array_key_exists('manager_id', $data) && $data['manager_id'] !== null
                ? (int) $data['manager_id']
                : null,
            managerSubmitted: array_key_exists('manager_id', $data),
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null
                ? (int) $data['parent_id']
                : null,
            parentIdSubmitted: array_key_exists('parent_id', $data),
            users: array_key_exists('users', $data) ? array_map('intval', (array) $data['users']) : null,
            operationalSites: array_key_exists('operational_sites', $data)
                ? array_map('intval', (array) $data['operational_sites'])
                : null,
            companyIds: array_key_exists('company_ids', $data) && $data['company_ids'] !== null
                ? array_map('intval', (array) $data['company_ids'])
                : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }

    public function hasType(): bool
    {
        return $this->typeSubmitted;
    }

    public function hasManager(): bool
    {
        return $this->managerSubmitted;
    }

    public function hasParentId(): bool
    {
        return $this->parentIdSubmitted;
    }

    public function hasUsers(): bool
    {
        return $this->users !== null;
    }

    public function hasOperationalSites(): bool
    {
        return $this->operationalSites !== null;
    }

    /**
     * `company_ids: null` is treated as "not submitted" (the pivot is only
     * synced when an array arrives; `[]` detaches every company).
     */
    public function hasCompanies(): bool
    {
        return $this->companyIds !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update (framework array boundary). `type`
     * is handled separately by the Service (boolean remap); `users`/
     * `operationalSites` are synced separately.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->managerSubmitted) {
            $attributes['manager_id'] = $this->managerId;
        }

        if ($this->parentIdSubmitted) {
            $attributes['parent_id'] = $this->parentId;
        }

        // Spec 0280: the node's OWN flag, written verbatim; the effective
        // value is resolved at read time (BusinessFunctionActivity).
        if ($this->isActive !== null) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
