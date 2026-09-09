<?php

namespace App\Models;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EmploymentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's employment profile (spec 0015): Profile (manager flag, job
 * description, reports-to), Contractual relationship (type, company,
 * qualification, dates) and Contractual data (daily minutes). One row per
 * user (hasOne on User via HasEmployment).
 *
 * Two of its sections are no longer columns on this table: the site
 * membership (spec 0103) lives on the
 * `employment_profile_operational_site` pivot (see operationalSites()), and
 * the business function (spec 0111 D-1) on the `employment_product_lines`
 * rows, paired with a product category (see productLines()).
 */
#[Fillable([
    'user_id',
    'is_manager',
    'job_description',
    'reports_to_id',
    'relationship_type',
    'company_id',
    'qualification_type',
    'hired_at',
    'terminated_at',
    'standard_daily_minutes',
    'break_daily_minutes',
])]
class EmploymentProfile extends BaseModel
{
    /** @use HasFactory<EmploymentProfileFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
            'relationship_type' => RelationshipTypeEnum::class,
            'qualification_type' => QualificationTypeEnum::class,
            'hired_at' => 'date:Y-m-d',
            'terminated_at' => 'date:Y-m-d',
            'standard_daily_minutes' => 'integer',
            'break_daily_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The manager this employee reports to, if any (self-referencing on User).
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * All the sites this profile is a member of (spec 0103): at most one
     * PHYSICAL (`is_primary` true) plus any number of REMOTE ones, on the
     * `employment_profile_operational_site` pivot.
     */
    public function operationalSites(): BelongsToMany
    {
        return $this->belongsToMany(OperationalSite::class, 'employment_profile_operational_site')
            ->withPivot('is_primary');
    }

    /**
     * The at-most-one PHYSICAL site (D-3). Not a BelongsTo: the source of
     * truth is the pivot flag, not a column on this table. The invariant
     * itself ("at most one") is enforced by EmploymentWriter (D-10), not
     * here.
     */
    public function primaryOperationalSite(): BelongsToMany
    {
        return $this->operationalSites()->wherePivot('is_primary', true);
    }

    /**
     * The zero-or-more REMOTE sites (D-1): operative exactly like the
     * physical one for every consumer (UserService::forSelect,
     * LeadOperatorDistributor::operatorIdsForSite).
     */
    public function remoteOperationalSites(): BelongsToMany
    {
        return $this->operationalSites()->wherePivot('is_primary', false);
    }

    /**
     * The competence of this profile (spec 0111): N rows pairing a business
     * function with a product category, the single source of both halves
     * since `business_function_id` was dropped (D-1). The name is load
     * bearing: ProductLineWriter::sync() reaches the collection through
     * `$owner->productLines()`, the same way every other owner exposes it.
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(EmploymentProductLine::class);
    }

    /**
     * Read-only proxy onto the pivot for the field-permission catalogue
     * (spec 0103 D-9, replacing the former `operational_site_id` column):
     * EnforcesFieldPermissions::readNestedPath() resolves
     * `employment.primary_operational_site_id` down to this accessor via
     * Model::getAttribute(), and needs a scalar id back, not a Model —
     * without it a resubmit of the SAME site on a locked field would always
     * look "changed" (spec 0008) and 422 spuriously (AC-013).
     *
     * PUBLIC (not the usual `protected` accessor convention), same reason as
     * OperationalSite::line1() &c.: EnforcesFieldPermissions::isRelation()
     * calls the method directly to probe whether it is a Relation — a
     * protected method would fatal on that external call.
     *
     * Reads off the already-loaded operationalSites collection rather than
     * primaryOperationalSite()->first(), so that when the caller has
     * eager-loaded operationalSites this triggers no extra query, and a
     * second call to remoteOperationalSiteIds() below reuses the same
     * cached collection (mirrors OperationalSite::primaryAddress()).
     */
    public function primaryOperationalSiteId(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->operationalSites
                ->first(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary)
                ?->id
        );
    }

    /**
     * Read-only proxy onto the pivot, counterpart of
     * primaryOperationalSiteId() above for the remote memberships (spec 0103
     * D-9). Same PUBLIC-accessor and no-N+1 reasoning applies.
     *
     * @return array<int, int>
     */
    public function remoteOperationalSiteIds(): Attribute
    {
        return Attribute::get(
            fn (): array => $this->operationalSites
                ->reject(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary)
                ->pluck('id')
                ->all()
        );
    }
}
