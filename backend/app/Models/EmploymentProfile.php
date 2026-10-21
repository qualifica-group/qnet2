<?php

namespace App\Models;

use App\Enums\RelationshipTypeEnum;
use App\Enums\WorkingTimeEnum;
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
 * Three of its sections are no longer columns on this table: the site
 * membership (spec 0103) lives on the
 * `employment_profile_operational_site` pivot (see operationalSites()), the
 * business function (spec 0111 D-1) on the `employment_product_lines` rows,
 * paired with a product category (see productLines()), and the reports-to
 * relationship (spec 0166 D-2) on the `employment_profile_manager` pivot
 * (see reportsTo()) — a user may now report to several managers, not just
 * one.
 */
#[Fillable([
    'user_id',
    'covers_all_product_categories',
    'is_assignable',
    'relationship_type',
    'contract_type_id',
    'working_time',
    'contract_expires_on',
    'company_id',
    'iban',
    'iban_holder',
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
     * The IBAN is encrypted at rest (spec 0272 D-6). The IBAN and its holder are
     * compensation data (D-5): hidden so the activity log (which excludes
     * hidden attributes) and default serialization never carry them.
     *
     * @var list<string>
     */
    protected $hidden = ['iban', 'iban_holder'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'covers_all_product_categories' => 'boolean',
            'is_assignable' => 'boolean',
            'relationship_type' => RelationshipTypeEnum::class,
            'working_time' => WorkingTimeEnum::class,
            'contract_expires_on' => 'date:Y-m-d',
            'iban' => 'encrypted',
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
     * The zero-or-more managers this employee reports to (spec 0166 D-2/D-3,
     * replacing the single `reports_to_id` FK), ordered by name so every
     * consumer (EmploymentResource, the field-permission accessor below)
     * reads them in a stable, user-facing order without sorting itself.
     */
    public function reportsTo(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employment_profile_manager', 'employment_profile_id', 'user_id')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Copy of the contract type of the condition in force (spec 0290 D-4),
     * written only by EmploymentContractService::syncCurrent().
     *
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    /**
     * Contractual condition history (spec 0272 D-1), latest start first.
     *
     * @return HasMany<EmploymentContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderByDesc('starts_on');
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
     * LeadOperatorDistributor::operatorIdsBySite).
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
     *
     * A row's `product_category_id` may be null (spec 0129 D-3: "every
     * category of this row's function"), and the whole collection is
     * deliberately empty when `covers_all_product_categories` is true (D-2) —
     * see App\Services\Assignment\OperatorCompetence for how both are read.
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(EmploymentProductLine::class);
    }

    /**
     * The job roles assigned to this person (spec 0271 D-2).
     *
     * @return BelongsToMany<JobRole, $this>
     */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'employment_profile_job_role');
    }

    /**
     * The business functions this person belongs to (spec 0275 D-3). The pivot
     * `business_function_user` is keyed on `user_id`, so the parent key is the
     * profile's `user_id`: the membership survives the profile row (AC-008).
     *
     * @return BelongsToMany<BusinessFunction, $this>
     */
    public function businessFunctions(): BelongsToMany
    {
        return $this->belongsToMany(BusinessFunction::class, 'business_function_user', 'user_id', 'business_function_id', 'user_id');
    }

    /**
     * Read-only proxy onto the functions this person manages (spec 0276), for
     * the field-permission catalogue; reads the owner's `managedBusinessFunctions`
     * relation, which the request eager-loads (and links back via `user`).
     *
     * @return Attribute<array<int, int>, never>
     */
    public function managedBusinessFunctionIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->user->managedBusinessFunctions->pluck('id')->all());
    }

    /**
     * Read-only proxy onto the membership for the field-permission catalogue
     * (spec 0275), same reason as jobRoleIds() below.
     *
     * @return Attribute<array<int, int>, never>
     */
    public function businessFunctionIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->businessFunctions->pluck('id')->all());
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

    /**
     * Read-only proxy onto the `jobRoles` pivot (spec 0271 D-10), the same
     * reason as reportsToIds() below: EnforcesFieldPermissions resolves
     * `employment.job_role_ids` down to this accessor and needs a plain array.
     * Reads off the already-loaded `jobRoles` collection.
     *
     * @return Attribute<array<int, int>, never>
     */
    public function jobRoleIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->jobRoles->pluck('id')->all());
    }

    /**
     * Read-only proxy onto the `reportsTo` pivot (spec 0166 D-3, replacing
     * the former `reports_to_id` column): EnforcesFieldPermissions::
     * readNestedPath() resolves `employment.reports_to_ids` down to this
     * accessor via Model::getAttribute(), and needs a plain array back, not a
     * Collection of Models — without it a resubmit of the SAME set on a
     * locked field would always look "changed" and 422 spuriously (mirrors
     * remoteOperationalSiteIds() above).
     *
     * PUBLIC, same reason as remoteOperationalSiteIds(): EnforcesFieldPermissions
     * ::isRelation() calls the method directly to probe whether it is a
     * Relation — a protected method would fatal on that external call.
     *
     * Reads off the already-loaded reportsTo collection rather than issuing
     * its own query, so eager-loading `employment.reportsTo` covers this too.
     *
     * @return array<int, int>
     */
    public function reportsToIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->reportsTo->pluck('id')->all());
    }
}
