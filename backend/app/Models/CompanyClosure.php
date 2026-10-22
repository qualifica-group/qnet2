<?php

namespace App\Models;

use App\Enums\CompanyClosureKind;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CompanyClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company closure (spec 0304 D-13): valid for all operational sites or for
 * the sites of the pivot. `absence_type_id` is set only for collective leave.
 */
#[Fillable([
    'name', 'starts_on', 'ends_on', 'kind', 'absence_type_id', 'applies_to_all_sites', 'repeats_yearly', 'created_by',
])]
class CompanyClosure extends BaseModel
{
    /** @use HasFactory<CompanyClosureFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => CompanyClosureKind::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'applies_to_all_sites' => 'boolean',
            'repeats_yearly' => 'boolean',
        ];
    }

    /**
     * The one applicability rule (spec 0304 D-13): valid for all sites, or for at least one of the
     * given site ids of a person (a person without sites only gets the all-sites closures).
     * Needs `operationalSites` loaded.
     *
     * @param  array<int, int>  $siteIds
     */
    public function appliesToSiteIds(array $siteIds): bool
    {
        return $this->applies_to_all_sites || array_intersect($siteIds, $this->operationalSites->modelKeys()) !== [];
    }

    /** A closure whose first day is today or past is started: it can no longer be edited (D-15). */
    public function isStarted(): bool
    {
        return $this->starts_on->format('Y-m-d') <= today()->format('Y-m-d');
    }

    /**
     * @return BelongsTo<AbsenceType, $this>
     */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }

    /**
     * @return BelongsToMany<OperationalSite, $this>
     */
    public function operationalSites(): BelongsToMany
    {
        return $this->belongsToMany(OperationalSite::class, 'company_closure_operational_site');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AbsenceRequest, $this>
     */
    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }
}
