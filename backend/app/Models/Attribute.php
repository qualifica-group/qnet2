<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global, reusable dynamic-attribute catalogue entry (spec 0017, aligned to
 * the custom fields' presentation shape — spec 0021): a typed field (`type`
 * is one of App\CustomFields\FieldTypeRegistry's keys) assignable to any
 * number of product categories via the `attribute_category` pivot.
 */
#[Fillable([
    'code', 'name', 'type', 'description', 'help_text', 'placeholder', 'icon',
    'config', 'relation_target',
])]
class Attribute extends BaseModel
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'relation_target' => 'array',
            // Spec 0013 — external data migration: the source system's id for a
            // migrated attribute, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The discrete value list, ENUM-typed attributes only.
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }

    /**
     * Categories this attribute is directly assigned to (own assignments —
     * NOT including a category that only inherits it from an ancestor).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'attribute_category', 'attribute_id', 'category_id')
            ->withPivot(['is_required', 'sort_order', 'context'])
            ->withTimestamps();
    }
}
