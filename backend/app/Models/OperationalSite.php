<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAddresses;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OperationalSiteFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Operational site entity (spec 0011 — "Sedi operative"): a physical
 * location identified entirely by its address (via HasAddresses, used here as
 * a single owned row rather than a real multi-address list — the invariant is
 * enforced by AddressService/OperationalSiteService, not the schema). No own
 * name/label column: the site IS its address (grid identity = comune + via),
 * mirroring Company.
 */
class OperationalSite extends BaseModel
{
    /** @use HasFactory<OperationalSiteFactory> */
    use HasAddresses, HasFactory, LogsModelActivity;

    protected $fillable = [
        // The site's own free-text label. The site is otherwise identified by
        // its address (spec 0011); `alias` exists because the legacy import
        // carries a site name in its `comune` field that is not a real city.
        'alias',
        // Spec 0135: an inactive site stays on its linked records but is no
        // longer offered by the for-select pickers.
        'is_active',
    ];

    /**
     * Spec 0013 — external data migration: the source system's id for a
     * migrated operational site, guarded (not in $fillable) so it is only
     * ever set by property assignment post-create.
     */
    protected $casts = [
        'old_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The site's single address, read off the `addresses` morph (first
     * primary row, falling back to any owned row). Convenience accessor for
     * callers that already eager-loaded `addresses`; does not itself trigger
     * a query when the relation is loaded.
     */
    protected function primaryAddress(): Attribute
    {
        return Attribute::get(function (): ?Address {
            $addresses = $this->addresses;

            return $addresses->firstWhere('is_primary', true) ?? $addresses->first();
        });
    }

    /**
     * Read-only proxies onto the primary address' columns, keyed exactly like
     * the flat write payload (line1/postal_code/country_id/state_id/
     * province_id/city_id — spec 0011 has no nested `address` object).
     *
     * These exist so EnforcesFieldPermissions' generic value-diff (spec 0008 —
     * "resubmitting the SAME value for a locked field is a no-op, not a 422")
     * reads the site's REAL current value for each field: without them, every
     * field would read as null (none is a genuine OperationalSite column or
     * relation), so any non-empty submission would always look "changed".
     * PUBLIC (not the usual `protected` accessor convention) because
     * EnforcesFieldPermissions::readTopLevel() calls the method directly to
     * probe whether it is a Relation — a protected method would fatal on that
     * external call.
     */
    public function line1(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->primaryAddress?->line1);
    }

    public function postalCode(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->primaryAddress?->postal_code);
    }

    public function countryId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->primaryAddress?->country_id);
    }

    public function stateId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->primaryAddress?->state_id);
    }

    public function provinceId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->primaryAddress?->province_id);
    }

    public function cityId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->primaryAddress?->city_id);
    }

    /**
     * The leads scoped to this site (spec 0024, BR-2/D-4: restrict-on-delete
     * — OperationalSiteService::delete() guards on this before deleting).
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * The business functions this site is associated to (spec 0010 REV),
     * inverse of BusinessFunction::operationalSites(). Feeds the
     * operational-sites/for-select `business_function_id` scope (spec 0040
     * BR-4).
     */
    public function businessFunctions(): BelongsToMany
    {
        return $this->belongsToMany(BusinessFunction::class, 'business_function_operational_site');
    }
}
