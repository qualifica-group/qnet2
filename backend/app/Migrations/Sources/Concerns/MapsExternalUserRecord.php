<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\EmploymentData;
use App\Enums\ContactTypeEnum;
use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;

/**
 * Field-mapping helpers for UsersSource (spec 0013): translate one raw external
 * user record into the qnet value objects consumed by UserService::create()
 * (roles, primary address, contacts, employment, is_active). Extracted from the
 * source itself to keep it under the file-size budget; every relational
 * reference is remapped via `old_id` on the using AbstractMigrationSource.
 *
 * The address/contact mapping (identical for every personal-data owner) lives
 * in the shared MapsExternalProfileRecord; only the user-specific roles and
 * employment mapping stays here.
 *
 * @phpstan-require-extends AbstractMigrationSource
 *
 * @property-read MigrationGeoResolver $geoResolver
 */
trait MapsExternalUserRecord
{
    use MapsExternalProfileRecord;

    /**
     * The external role references, as external ids, taken from the record's
     * `roles` array of `{id, name}` objects (the external contract's shape).
     * A flat `roles: [id, ...]` list is also tolerated. Each id is remapped to
     * a qnet role via `old_id` in resolveRoleNames().
     *
     * @param  array<string, mixed>  $record
     * @return array<int, int|string>
     */
    private function externalRoleIds(array $record): array
    {
        $roles = $record['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $ids = [];

        foreach ($roles as $role) {
            $externalId = is_array($role) ? ($role['id'] ?? null) : $role;

            if ($externalId !== null && $externalId !== '') {
                $ids[] = $externalId;
            }
        }

        return $ids;
    }

    /**
     * Remap the external role references to qnet role names via `old_id`. A
     * reference that resolves to no migrated role becomes a non-fatal
     * warning (the user is still created, just without that role).
     *
     * @param  array<int, int|string>  $externalRoleIds
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function resolveRoleNames(array $externalRoleIds): array
    {
        $names = [];
        $warnings = [];

        foreach ($externalRoleIds as $externalRoleId) {
            $roleId = $this->resolveOldId(Role::class, $externalRoleId);

            if ($roleId === null) {
                $warnings[] = "Unresolved role reference (external id {$externalRoleId}).";

                continue;
            }

            /** @var Role|null $role */
            $role = Role::query()->find($roleId);

            if ($role !== null) {
                $names[] = $role->name;
            }
        }

        return [$names, $warnings];
    }

    /**
     * Build the user's contact channels: a personal email plus a
     * business/personal phone, each keyed by a free-text label (there is no
     * business/personal dimension on ContactTypeEnum itself) and each flagged
     * primary. As the two phones share a type, the "one primary per owner + type"
     * invariant (ContactService) keeps the last of them primary. Delegates to the
     * shared, candidate-driven builder (MapsExternalProfileRecord).
     *
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        return $this->buildContactInputs($record, [
            ['field' => 'personal_email', 'type' => ContactTypeEnum::Email, 'label' => 'Personale'],
            ['field' => 'business_phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Aziendale'],
            ['field' => 'personal_phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Personale'],
        ]);
    }

    /**
     * Build the user's employment profile only when at least one employment
     * field was supplied (an absent employment section means "no employment
     * row", mirroring the wire contract's tri-state semantics). Every
     * relational reference (manager/company/operational site) resolves via
     * `old_id`; an unresolved reference is a non-fatal warning and leaves
     * that link null. An unknown relationship/qualification type is likewise
     * a non-fatal warning.
     *
     * The external system carries a SINGLE site per user (spec 0103 context):
     * it becomes the PHYSICAL membership, so `primaryOperationalSiteIdProvided`
     * is always true on a create — the resolved id, or null when absent/
     * unresolved, is the whole desired state of that side. The remote side is
     * left unprovided: the external system has no notion of it.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: ?EmploymentData, 1: array<int, string>}
     */
    private function buildEmployment(array $record): array
    {
        if (! $this->hasEmploymentPayload($record)) {
            return [null, []];
        }

        $warnings = [];

        $reportsToId = $this->resolveEmploymentRelation(User::class, $record['reports_to_id'] ?? null, 'reports_to_id', $warnings);
        $companyId = $this->resolveEmploymentRelation(Company::class, $record['company_id'] ?? null, 'company_id', $warnings);
        $operationalSiteId = $this->resolveEmploymentRelation(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings);

        $employment = new EmploymentData(
            isManager: (bool) ($record['is_manager'] ?? false),
            jobDescription: $this->blankToNull($record['job_description'] ?? null),
            reportsToId: $reportsToId,
            relationshipType: $this->resolveRelationshipType($record['relationship_type'] ?? null, $warnings),
            companyId: $companyId,
            primaryOperationalSiteIdProvided: true,
            primaryOperationalSiteId: $operationalSiteId,
            qualificationType: $this->resolveQualificationType($record['qualification_type'] ?? null, $warnings),
            hiredAt: $this->blankToNull($record['hired_at'] ?? null),
            terminatedAt: $this->blankToNull($record['terminated_at'] ?? null),
            standardDailyMinutes: $this->blankToInt($record['standard_daily_minutes'] ?? null),
            breakDailyMinutes: $this->blankToInt($record['break_daily_minutes'] ?? null),
        );

        return [$employment, $warnings];
    }

    /**
     * Self-healing re-import (spec 0013 idempotency, extended): a user whose
     * `old_id` already exists is not blindly skipped — any employment relation
     * still NULL on the existing row is back-filled from the external record
     * via `old_id`, without overwriting a value already set or duplicating
     * anything. This resolves the two cases a single create-time pass cannot:
     * a user imported BEFORE its parents (company / site) were migrated, and
     * the self-referential manager (`reports_to_id`) whose record is processed
     * after the subordinate — both are fixed by simply running the users
     * import again once every parent exists.
     *
     * The site membership (spec 0103) is no longer one of these NULL columns:
     * its own "fill once, never overwrite" counterpart is
     * backfillPhysicalSite() below, keyed off the pivot instead of a column —
     * see resolveAndBackfillEmployment() for how the two combine.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function reconcileEmployment(int|string $externalId, array $record): array
    {
        [$filled, $warnings] = $this->resolveAndBackfillEmployment($externalId, $record);

        if ($filled > 0) {
            $warnings[] = 'Relinked '.$filled.' employment reference(s) on re-import.';
        }

        return $warnings;
    }

    /**
     * End-of-import relinking pass (spec 0013, ordering-independent): a user
     * whose manager/parent appeared LATER in the SAME run had that reference
     * left null on the first pass; now that every row exists, its still-null
     * employment relations are back-filled — no manual re-run needed. The
     * unresolved-reference warnings were already reported on the first pass,
     * so only the successful relink is surfaced (its message, or null).
     *
     * @param  array<string, mixed>  $record
     */
    private function relinkEmployment(int|string $externalId, array $record): ?string
    {
        [$filled] = $this->resolveAndBackfillEmployment($externalId, $record);

        return $filled > 0 ? 'Relinked '.$filled.' employment reference(s) after import.' : null;
    }

    /**
     * Re-resolve the record's employment relations and back-fill any that are
     * still unset on the already-existing user: the plain columns via
     * nullRelationBackfill(), the site membership via backfillPhysicalSite()
     * (spec 0103 — a pivot row, not a column, so it needs its own gate).
     * Returns how many references were filled in total and the resolution
     * warnings — shared by the re-import skip path and the end-of-import
     * relinking pass, which surface them differently.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: int, 1: array<int, string>}
     */
    private function resolveAndBackfillEmployment(int|string $externalId, array $record): array
    {
        if (! $this->hasEmploymentPayload($record)) {
            return [0, []];
        }

        /** @var User|null $user */
        $user = User::query()->where('old_id', $externalId)->with('employment.operationalSites')->first();

        if ($user?->employment === null) {
            return [0, []];
        }

        [$employment, $warnings] = $this->buildEmployment($record);

        if ($employment === null) {
            return [0, $warnings];
        }

        $fill = $this->nullRelationBackfill($user->employment, $employment);
        $filled = count($fill);

        if ($fill !== []) {
            $user->employment->update($fill);
        }

        if ($this->backfillPhysicalSite($user->employment, $employment)) {
            $filled++;
        }

        return [$filled, $warnings];
    }

    /**
     * The plain employment relation columns still NULL on the existing row
     * that now resolve to a qnet id — filled once, never overwriting. The
     * site membership (spec 0103) is no longer one of these columns: its own
     * counterpart is backfillPhysicalSite() below. A manager can never report
     * to someone (the EmploymentWriter invariant), so `reports_to_id` is
     * back-filled only for non-managers.
     *
     * @return array<string, int>
     */
    private function nullRelationBackfill(EmploymentProfile $existing, EmploymentData $desired): array
    {
        $candidates = [
            'company_id' => $desired->companyId,
        ];

        if (! $desired->isManager) {
            $candidates['reports_to_id'] = $desired->reportsToId;
        }

        $fill = [];

        foreach ($candidates as $column => $resolvedId) {
            if ($resolvedId !== null && $existing->{$column} === null) {
                $fill[$column] = $resolvedId;
            }
        }

        return $fill;
    }

    /**
     * Self-healing counterpart of nullRelationBackfill() for the site
     * membership (spec 0103 D-5/AC-031): a profile with no PHYSICAL site yet
     * gets the resolved external site attached to
     * `employment_profile_operational_site` as `is_primary = true` — the same
     * "fill once, never overwrite" property translated from a NULL column to
     * an absent pivot row. A profile that already has a physical site
     * (assigned by hand, or by an earlier import pass) is left untouched:
     * this is what keeps a manual assignment surviving re-import, and what
     * makes re-importing the same record idempotent — the attach only ever
     * happens the first time a physical site is missing.
     *
     * `syncWithoutDetaching` (not `attach`) so that a site already present as
     * REMOTE on this profile is promoted in place instead of colliding with
     * the pivot's unique (employment_profile_id, operational_site_id) pair.
     */
    private function backfillPhysicalSite(EmploymentProfile $existing, EmploymentData $desired): bool
    {
        if ($desired->primaryOperationalSiteId === null || $existing->primary_operational_site_id !== null) {
            return false;
        }

        $existing->operationalSites()->syncWithoutDetaching([
            $desired->primaryOperationalSiteId => ['is_primary' => true],
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function hasEmploymentPayload(array $record): bool
    {
        $employmentFields = [
            'is_manager', 'job_description', 'reports_to_id', 'relationship_type',
            'company_id', 'operational_site_id', 'qualification_type',
            'hired_at', 'terminated_at', 'standard_daily_minutes', 'break_daily_minutes',
        ];

        foreach ($employmentFields as $field) {
            if (array_key_exists($field, $record) && $record[$field] !== null && $record[$field] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<User|Company|OperationalSite>  $modelClass
     * @param  array<int, string>  $warnings
     */
    private function resolveEmploymentRelation(string $modelClass, mixed $externalRef, string $field, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId($modelClass, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved {$field} (external id {$externalRef}).";
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveRelationshipType(mixed $raw, array &$warnings): ?RelationshipTypeEnum
    {
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return null;
        }

        $case = RelationshipTypeEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved relationship_type '{$value}'.";
        }

        return $case;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveQualificationType(mixed $raw, array &$warnings): ?QualificationTypeEnum
    {
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return null;
        }

        $case = QualificationTypeEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved qualification_type '{$value}'.";
        }

        return $case;
    }

    /**
     * Absent/blank -> true (a migrated user is active unless the source
     * explicitly opts out), mirroring CreateUserData's own default. Accepts a
     * JSON boolean as well as the "1"/"0"/"true"/"false" string encodings.
     *
     * @param  array<string, mixed>  $record
     */
    private function resolveActive(array $record): bool
    {
        $value = $record['is_active'] ?? null;

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
