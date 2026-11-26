<?php

namespace App\Models;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CommissionConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'name', 'recipient_role', 'application_scope',
    'recipient_type', 'recipient_id', 'commission_type', 'supplier_commission_direction', 'value',
    'priority', 'valid_from', 'valid_until', 'status', 'internal_note',
])]
class CommissionConfiguration extends BaseModel
{
    /** @use HasFactory<CommissionConfigurationFactory> */
    use HasFactory, LogsModelActivity;

    protected function casts(): array
    {
        return [
            'recipient_role' => CommissionRecipientRole::class,
            'application_scope' => CommissionApplicationScope::class,
            'commission_type' => CommissionType::class,
            'supplier_commission_direction' => SupplierCommissionDirection::class,
            'value' => 'decimal:4',
            'priority' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'status' => CommissionConfigurationStatus::class,
        ];
    }

    /** Spec 0430: the categories a PRODUCT_CATEGORY rule covers. */
    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'commission_configuration_product_category');
    }

    /** Spec 0430: the products a PRODUCT rule covers. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'commission_configuration_product');
    }

    /**
     * The ids behind `product_category_ids` / `product_ids`, the shape the field
     * permission guard compares a submitted list against.
     *
     * @return Attribute<list<int>, never>
     */
    protected function productCategoryIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->relatedIds('productCategories', 'product_categories.id'));
    }

    /** @return Attribute<list<int>, never> */
    protected function productIds(): Attribute
    {
        return Attribute::get(fn (): array => $this->relatedIds('products', 'products.id'));
    }

    /** @return list<int> */
    private function relatedIds(string $relation, string $qualifiedKey): array
    {
        $ids = $this->relationLoaded($relation)
            ? $this->getRelation($relation)->pluck('id')
            : $this->{$relation}()->pluck($qualifiedKey);

        return $ids->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    /**
     * The single recipient this rule is scoped to (spec 0089 D-1), or no
     * relation loaded when the rule is a plain role-wide default.
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function appliedCommissions(): HasMany
    {
        return $this->hasMany(QuoteLineCommission::class);
    }
}
