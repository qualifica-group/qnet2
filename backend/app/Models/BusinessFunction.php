<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\BusinessFunctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Business function (spec 0010): a name, a mutually-exclusive type
 * (is_business_unit XOR is_business_service, both false meaning "neither"),
 * an optional manager, an optional set of associated users, an optional
 * parent/child hierarchy (spec 0010 REV, mirroring ProductCategory) and an
 * optional many-to-many with operational sites.
 */
#[Fillable(['name', 'is_business_unit', 'is_business_service', 'manager_id', 'parent_id'])]
class BusinessFunction extends BaseModel
{
    /** @use HasFactory<BusinessFunctionFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_business_unit' => 'boolean',
            'is_business_service' => 'boolean',
            // Spec 0013 — external data migration: the source system's id for
            // a migrated business function, guarded (not in $fillable) so it
            // is only ever set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The function's single responsible user, if any.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The users associated to this function (0..n), via the pivot table.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_function_user');
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'business_function_company');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * The operational sites associated to this function (0..n), via the
     * pivot table.
     */
    public function operationalSites(): BelongsToMany
    {
        return $this->belongsToMany(OperationalSite::class, 'business_function_operational_site');
    }

    /**
     * The opportunities against this business function, via at least one
     * `opportunity_product_lines` row (spec 0040 amendment rev.3, BR-3:
     * restrict-on-delete — BusinessFunctionService::delete() guards on this
     * before deleting). A BelongsToMany rather than a direct HasMany since
     * the FK now lives on the pivot row, not on `opportunities` itself.
     *
     * @return BelongsToMany<Opportunity, $this>
     */
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_product_lines');
    }
}
