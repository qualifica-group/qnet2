<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\EmploymentProductLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "funzione aziendale" + "categoria prodotto" row of a user's
 * competence (spec 0111): SUBSTITUTES the single
 * `employment_profiles.business_function_id` column plus the category-only
 * pivot of spec 0110 with a one-to-many collection, the same shape every
 * other owner carries (OpportunityProductLine, CampaignProductLine,
 * ProjectProductLine). No activity log: this is a pure child collection of
 * the EmploymentProfile, which already logs its own changes.
 *
 * `employment_profile_id` is deliberately NOT fillable (D-7): the field
 * permission catalogue projects each row onto the fillables
 * (EnforcesFieldPermissions::readNestedPath()), so an owner FK in there
 * would make a resubmit of the SAME rows on a readonly field look "changed"
 * and 422 spuriously (spec 0008 AC-013). The HasMany relation sets the FK
 * by itself on create.
 *
 * `share_percentage` (spec 0346) is the person's informative share of
 * commitment on the row, 1..100; a profile's rows add up to 100
 * (CompetenceLineSetValidator). Fillable, so a changed share on a readonly
 * field is a change like any other.
 */
#[Fillable(['business_function_id', 'product_category_id', 'share_percentage'])]
class EmploymentProductLine extends BaseModel
{
    /** @use HasFactory<EmploymentProductLineFactory> */
    use HasFactory;

    /** The share a profile's rows add up to (spec 0346 D-2). */
    public const int SHARE_TOTAL = 100;

    /**
     * Mirrors the column default, so a row created without a share reads the
     * same in memory as after a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'share_percentage' => self::SHARE_TOTAL,
    ];

    protected function casts(): array
    {
        return [
            'share_percentage' => 'integer',
        ];
    }

    public function employmentProfile(): BelongsTo
    {
        return $this->belongsTo(EmploymentProfile::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
