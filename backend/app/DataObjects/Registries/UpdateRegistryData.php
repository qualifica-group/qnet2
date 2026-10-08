<?php

namespace App\DataObjects\Registries;

/**
 * Validated payload for a partial (PATCH) registry update
 * (PUT/PATCH /api/registries/{registry}, spec 0020).
 *
 * Declared DTO (no "magic flying array") so the UpdateRegistryRequest ->
 * RegistryService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects. Every scalar is a legitimately nullable VALUE (e.g.
 * clearing `source_id`/`agreement_status`), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the `*Submitted`
 * flags carry that distinction explicitly (mirrors UpdateReferentData). The
 * three pivot id arrays follow CreateRegistryData/UpdateSectorData's
 * simpler convention instead: null means "not submitted, leave untouched",
 * an array (including empty) is an authoritative sync.
 */
final readonly class UpdateRegistryData
{
    /**
     * @param  array<int, int>|null  $sectorIds
     * @param  array<int, int>|null  $referentIds
     * @param  array<int, int|null>|null  $managerSlots
     */
    public function __construct(
        public ?int $sourceId = null,
        public bool $sourceIdSubmitted = false,
        public ?array $sectorIds = null,
        public ?array $referentIds = null,
        public ?array $managerSlots = null,
        public ?int $supervisorId = null,
        public bool $supervisorIdSubmitted = false,
        public ?int $commercialId = null,
        public bool $commercialIdSubmitted = false,
        public ?int $reporterId = null,
        public bool $reporterIdSubmitted = false,
        public ?string $vatGroup = null,
        public bool $vatGroupSubmitted = false,
        public ?bool $isSupplier = null,
        public bool $isSupplierSubmitted = false,
        public ?bool $isQualifiedSupplier = null,
        public bool $isQualifiedSupplierSubmitted = false,
        public ?string $agreementStatus = null,
        public bool $agreementStatusSubmitted = false,
        public ?string $agreementNotes = null,
        public bool $agreementNotesSubmitted = false,
        public ?string $sizeClass = null,
        public bool $sizeClassSubmitted = false,
        public ?int $employeeCount = null,
        public bool $employeeCountSubmitted = false,
        public ?string $generalNotes = null,
        public bool $generalNotesSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateRegistryRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            sourceId: array_key_exists('source_id', $data) && $data['source_id'] !== null ? (int) $data['source_id'] : null,
            sourceIdSubmitted: array_key_exists('source_id', $data),
            sectorIds: array_key_exists('sector_ids', $data) ? array_map('intval', $data['sector_ids']) : null,
            referentIds: array_key_exists('referent_ids', $data) ? array_map('intval', $data['referent_ids']) : null,
            managerSlots: array_key_exists('manager_slots', $data)
                ? array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $data['manager_slots'])
                : null,
            supervisorId: array_key_exists('supervisor_id', $data) && $data['supervisor_id'] !== null ? (int) $data['supervisor_id'] : null,
            supervisorIdSubmitted: array_key_exists('supervisor_id', $data),
            commercialId: array_key_exists('commercial_id', $data) && $data['commercial_id'] !== null ? (int) $data['commercial_id'] : null,
            commercialIdSubmitted: array_key_exists('commercial_id', $data),
            reporterId: array_key_exists('reporter_id', $data) && $data['reporter_id'] !== null ? (int) $data['reporter_id'] : null,
            reporterIdSubmitted: array_key_exists('reporter_id', $data),
            vatGroup: array_key_exists('vat_group', $data) ? $data['vat_group'] : null,
            vatGroupSubmitted: array_key_exists('vat_group', $data),
            isSupplier: array_key_exists('is_supplier', $data) ? (bool) $data['is_supplier'] : null,
            isSupplierSubmitted: array_key_exists('is_supplier', $data),
            isQualifiedSupplier: array_key_exists('is_qualified_supplier', $data) ? (bool) $data['is_qualified_supplier'] : null,
            isQualifiedSupplierSubmitted: array_key_exists('is_qualified_supplier', $data),
            agreementStatus: array_key_exists('agreement_status', $data) ? $data['agreement_status'] : null,
            agreementStatusSubmitted: array_key_exists('agreement_status', $data),
            agreementNotes: array_key_exists('agreement_notes', $data) ? $data['agreement_notes'] : null,
            agreementNotesSubmitted: array_key_exists('agreement_notes', $data),
            sizeClass: array_key_exists('size_class', $data) ? $data['size_class'] : null,
            sizeClassSubmitted: array_key_exists('size_class', $data),
            employeeCount: array_key_exists('employee_count', $data) && $data['employee_count'] !== null ? (int) $data['employee_count'] : null,
            employeeCountSubmitted: array_key_exists('employee_count', $data),
            generalNotes: array_key_exists('general_notes', $data) ? $data['general_notes'] : null,
            generalNotesSubmitted: array_key_exists('general_notes', $data),
        );
    }

    public function hasSectorIds(): bool
    {
        return $this->sectorIds !== null;
    }

    public function hasReferentIds(): bool
    {
        return $this->referentIds !== null;
    }

    public function hasManagerSlots(): bool
    {
        return $this->managerSlots !== null;
    }

    /**
     * Only the registry's own scalar attributes the client actually
     * submitted, ready for a partial mass-assignment update. Pivot id arrays
     * and `name` (card-derived) are never included here.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->sourceIdSubmitted) {
            $attributes['source_id'] = $this->sourceId;
        }

        if ($this->supervisorIdSubmitted) {
            $attributes['supervisor_id'] = $this->supervisorId;
        }

        if ($this->commercialIdSubmitted) {
            $attributes['commercial_id'] = $this->commercialId;
        }

        if ($this->reporterIdSubmitted) {
            $attributes['reporter_id'] = $this->reporterId;
        }

        if ($this->vatGroupSubmitted) {
            $attributes['vat_group'] = $this->vatGroup;
        }

        if ($this->isSupplierSubmitted) {
            $attributes['is_supplier'] = $this->isSupplier;
        }

        if ($this->isQualifiedSupplierSubmitted) {
            $attributes['is_qualified_supplier'] = $this->isQualifiedSupplier;
        }

        if ($this->agreementStatusSubmitted) {
            $attributes['agreement_status'] = $this->agreementStatus;
        }

        if ($this->agreementNotesSubmitted) {
            $attributes['agreement_notes'] = $this->agreementNotes;
        }

        if ($this->sizeClassSubmitted) {
            $attributes['size_class'] = $this->sizeClass;
        }

        if ($this->employeeCountSubmitted) {
            $attributes['employee_count'] = $this->employeeCount;
        }

        if ($this->generalNotesSubmitted) {
            $attributes['general_notes'] = $this->generalNotes;
        }

        return $attributes;
    }
}
