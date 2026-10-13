<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseRequestPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchase request priority lookup entity (spec 0247, D-3): one of the four PURE purchase request
 * configurators — no `system_key`, no numeric weight, no protected row.
 * Every row is renameable/deletable, guarded only by "in use" by a purchase request
 * (D-8). `color` is a token of `App\Support\BadgeTokens::colors()`, `icon` a
 * name of `App\Support\BadgeTokens::icons()`, both validated server-side by
 * the FormRequest — never free text.
 */
#[Fillable(['name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'is_default'])]
class PurchaseRequestPriority extends BaseModel
{
    /** @use HasFactory<PurchaseRequestPriorityFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'is_default' => 'bool',
        ];
    }

    /**
     * The purchase requests classified under this priority — the "in use" set
     * `PurchaseRequestPriorityService::delete()` guards against (D-8b).
     *
     * @return HasMany<PurchaseRequest, $this>
     */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }
}
