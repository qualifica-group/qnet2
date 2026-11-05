<?php

namespace App\Models;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ChartAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group (Mastro) or account (Conto) of the managerial chart of accounts
 * (spec 0348). The `kind` is stored on groups only; an account inherits it
 * through its parent (effectiveKind()).
 */
#[Fillable(['level', 'parent_id', 'kind', 'code', 'name', 'description', 'is_active'])]
class ChartAccount extends BaseModel
{
    /** @use HasFactory<ChartAccountFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ChartAccountLevel::class,
            'kind' => ChartAccountKind::class,
            'is_active' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<ChartAccount, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ChartAccount, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CostType, $this>
     */
    public function costTypes(): HasMany
    {
        return $this->hasMany(CostType::class);
    }

    /**
     * @return HasMany<RevenueType, $this>
     */
    public function revenueTypes(): HasMany
    {
        return $this->hasMany(RevenueType::class);
    }

    /**
     * The group's own kind, or the parent group's for an account. Requires the
     * `parent` relation loaded on an account.
     */
    public function effectiveKind(): ?ChartAccountKind
    {
        return $this->level === ChartAccountLevel::Group ? $this->kind : $this->parent?->kind;
    }

    /**
     * Whether any child row hangs below this one (group: accounts; account:
     * cost or revenue sub-accounts).
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists()
            || $this->costTypes()->exists()
            || $this->revenueTypes()->exists();
    }
}
