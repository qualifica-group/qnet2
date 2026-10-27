<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named subset of the navigation menu ("App" in the UI, spec 0317). Named
 * Workspace because `App` collides with the root namespace.
 */
#[Fillable(['slug', 'name', 'description', 'icon', 'color', 'sort_order'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkspaceNavigationItem::class);
    }

    /**
     * Requires the `items` relation to be loaded.
     *
     * @return array<int, string>
     */
    public function navigationKeys(): array
    {
        return $this->items->pluck('navigation_key')->all();
    }
}
