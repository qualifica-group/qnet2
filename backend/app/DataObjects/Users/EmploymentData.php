<?php

namespace App\DataObjects\Users;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;

/**
 * The nested `employment` object submitted alongside a user write (spec
 * 0015): Profile / Contractual relationship / Contractual data.
 *
 * Tri-state wire semantics at the request boundary (ValidatesEmployment::
 * toEmployment()): the KEY absent yields a null DTO (leave the row
 * untouched); an explicit `employment: null` yields `EmploymentData::delete()`
 * (remove the row); a present object yields an upsert instance. Both create
 * and update funnel through the same EmploymentWriter — on create there is
 * never an existing row, so a `delete()` instance is a harmless no-op,
 * matching "absent or null => no row" for POST.
 *
 * The site membership (spec 0103) is NOT a column of `employment_profiles`
 * any more — it lives on the `employment_profile_operational_site` pivot —
 * so `primaryOperationalSiteId`/`remoteOperationalSiteIds` carry a SECOND,
 * per-field tri-state on top of the one above, tracked by their own
 * `*Provided` flag: key absent from the `employment.*` payload => that side
 * of the membership is left untouched; key present with null (primary) or
 * an empty/absent-from-array id (remote) => that side is cleared. This is
 * deliberately independent from the other scalar fields below, which have
 * no such flag and are always fully replaced by whatever the object carries
 * (absent sub-key there just means "set to null"), because those are plain
 * columns re-upserted wholesale on every write while the membership is a
 * separate set of pivot rows that EmploymentWriter must be told whether to
 * touch at all (see EmploymentWriter::syncSiteMemberships()).
 */
final readonly class EmploymentData
{
    /**
     * @param  array<int, int>  $remoteOperationalSiteIds
     */
    public function __construct(
        public bool $delete = false,
        public bool $isManager = false,
        public ?string $jobDescription = null,
        public ?int $reportsToId = null,
        public ?int $businessFunctionId = null,
        public ?RelationshipTypeEnum $relationshipType = null,
        public ?int $companyId = null,
        public bool $primaryOperationalSiteIdProvided = false,
        public ?int $primaryOperationalSiteId = null,
        public bool $remoteOperationalSiteIdsProvided = false,
        public array $remoteOperationalSiteIds = [],
        public ?QualificationTypeEnum $qualificationType = null,
        public ?string $hiredAt = null,
        public ?string $terminatedAt = null,
        public ?int $standardDailyMinutes = null,
        public ?int $breakDailyMinutes = null,
    ) {}

    /**
     * The intent to remove the employment row (explicit `employment: null`).
     */
    public static function delete(): self
    {
        return new self(delete: true);
    }

    /**
     * The row attributes for a mass-assignment upsert (framework array
     * boundary). Never called when $delete is true. Deliberately excludes
     * the site membership: those are pivot rows, not columns of this table
     * (spec 0103) — EmploymentWriter::syncSiteMemberships() applies them
     * separately, after this upsert.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'is_manager' => $this->isManager,
            'job_description' => $this->jobDescription,
            'reports_to_id' => $this->reportsToId,
            'business_function_id' => $this->businessFunctionId,
            'relationship_type' => $this->relationshipType,
            'company_id' => $this->companyId,
            'qualification_type' => $this->qualificationType,
            'hired_at' => $this->hiredAt,
            'terminated_at' => $this->terminatedAt,
            'standard_daily_minutes' => $this->standardDailyMinutes,
            'break_daily_minutes' => $this->breakDailyMinutes,
        ];
    }
}
