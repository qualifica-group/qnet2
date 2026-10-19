<?php

namespace App\DataObjects\Users;

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
 * deliberately independent from the other scalar fields below, because the
 * membership is a separate set of pivot rows that EmploymentWriter must be
 * told whether to touch at all (see EmploymentWriter::syncSiteMemberships()).
 *
 * `contractData` (the "Dati contrattuali" columns: hire and termination
 * dates, daily durations) holds only the keys the payload carried, so an
 * absent key leaves its column untouched and null clears it (spec 0283 D-5):
 * an actor whose field matrix hides them saves the record without wiping them.
 *
 * `reportsToIds` (spec 0166 D-2, replacing the single `reportsToId` column)
 * carries the SAME per-field tri-state, for the same reason: it is now a set
 * of pivot rows (`employment_profile_manager`), not a column, so
 * EmploymentWriter must be told whether to touch it at all
 * (`reportsToIdsProvided`) independently of every scalar field above.
 *
 * `productLines` (spec 0111, the assignment competence) carries the SAME
 * per-field tri-state, for the same reason: it is a set of child rows
 * (`employment_product_lines`) pairing a business function with a product
 * category, independent from the site membership and from the scalar columns.
 * Since spec 0111 D-1 it is also the ONLY source of a user's business
 * function — the former `businessFunctionId` column is gone. Spec 0129 D-3
 * lets a row's `product_category_id` be null ("every category of this row's
 * function").
 *
 * `jobRoleIds` (spec 0271 D-2, replacing the free-text `job_description`)
 * carries the SAME per-field tri-state as `reportsToIds`: a pivot
 * (`employment_profile_job_role`), absent = untouched, empty = cleared.
 *
 * `businessFunctionIds` (spec 0275 D-3) carries the same tri-state for the
 * membership pivot `business_function_user` (keyed on the user).
 *
 * `coversAllProductCategories` (spec 0129 D-1) is a THIRD way to widen the
 * competence: a plain scalar column, no tri-state (D-10 — absent on write
 * reads as false). EmploymentWriter enforces D-2 (true =>
 * the rows are cleared) rather than trusting the request to have emptied
 * `productLines` itself.
 *
 * `relationship_type`/`company_id` are NOT carried here any more (spec 0272
 * D-2): they are the copy of the current contract condition, written only by
 * the contract service. `iban`/`ibanHolder` (D-6) follow the per-field
 * tri-state of the pivots: absent = untouched, null = cleared; the request
 * only lets them through for an actor holding `users.manageCompensation`.
 *
 * `managedBusinessFunctionIds` (spec 0276 D-5) carries the same tri-state as
 * `businessFunctionIds` but is written on `business_functions.manager_id`, not
 * on a pivot of the profile.
 *
 * `isAssignable` (spec 0194 D-3) reads null on purpose: null = the key was absent, the column is left untouched (the DB default
 * true applies on a new row). Absent must never read as false, or any client
 * omitting the key would silently take the user out of every assignment pool.
 */
final readonly class EmploymentData
{
    /** The columns `contractData` may carry. */
    public const array CONTRACT_DATA_COLUMNS = ['hired_at', 'terminated_at', 'standard_daily_minutes', 'break_daily_minutes'];

    /**
     * @param  array<int, int>  $reportsToIds
     * @param  array<int, int>  $jobRoleIds
     * @param  array<int, int>  $businessFunctionIds
     * @param  array<int, int>  $managedBusinessFunctionIds
     * @param  array<int, int>  $remoteOperationalSiteIds
     * @param  array<int, array{business_function_id: int, product_category_id: int|null}>  $productLines
     * @param  array<string, string|int|null>  $contractData  column => value, only the provided CONTRACT_DATA_COLUMNS
     */
    public function __construct(
        public bool $delete = false,
        public bool $coversAllProductCategories = false,
        public ?bool $isAssignable = null,
        public bool $reportsToIdsProvided = false,
        public array $reportsToIds = [],
        public bool $primaryOperationalSiteIdProvided = false,
        public ?int $primaryOperationalSiteId = null,
        public bool $remoteOperationalSiteIdsProvided = false,
        public array $remoteOperationalSiteIds = [],
        public bool $productLinesProvided = false,
        public array $productLines = [],
        public bool $jobRoleIdsProvided = false,
        public array $jobRoleIds = [],
        public bool $businessFunctionIdsProvided = false,
        public array $businessFunctionIds = [],
        public bool $managedBusinessFunctionIdsProvided = false,
        public array $managedBusinessFunctionIds = [],
        public array $contractData = [],
        public bool $ibanProvided = false,
        public ?string $iban = null,
        public bool $ibanHolderProvided = false,
        public ?string $ibanHolder = null,
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
     * the site membership, the competence and the reports-to managers: those
     * are child/pivot rows, not columns of this table (spec 0103 / 0111 /
     * 0166) — EmploymentWriter::syncSiteMemberships(), syncProductLines() and
     * syncManagers() apply them separately, after this upsert.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = [
            'covers_all_product_categories' => $this->coversAllProductCategories,
            ...$this->contractData,
        ];

        if ($this->isAssignable !== null) {
            $attributes['is_assignable'] = $this->isAssignable;
        }

        if ($this->ibanProvided) {
            $attributes['iban'] = $this->iban;
        }

        if ($this->ibanHolderProvided) {
            $attributes['iban_holder'] = $this->ibanHolder;
        }

        return $attributes;
    }
}
