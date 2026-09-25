<?php

namespace App\Models;

use App\Enums\FilterViewVisibility;
use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user's named, saved AG Grid filter set for one table domain (spec 0007),
 * either private (owner only) or shared (every user who can view the table).
 *
 * Unlike UserTableFilter (the single "currently applied" filter state), a user
 * may save MANY named views per domain. Shared views are a real cross-user
 * access surface, so this model IS backed by a Policy
 * (TableFilterViewPolicy) — the one difference from its sibling.
 *
 * `filters` is restricted to the definition's filterable columns on every
 * write (TableFilterViewRequest) and re-filtered on every read
 * (TableFilterViewService), so it is never a SQL sink and can never widen the
 * SSRM filter allow-list. `advanced_filters` (spec 0032) is its sibling for
 * the second-level, backend-driven advanced-filter panel, restricted to
 * advancedFilterableIds() the same way. `rules` (spec 0158) is the generic
 * E/O custom-filter alternative: when present the view is a "custom filter"
 * view and `filters`/`advanced_filters` are saved empty (see
 * TableFilterViewService).
 */
class TableFilterView extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'domain', 'name', 'filters', 'visibility', 'advanced_filters', 'rules'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The users who favorited this view (spec 0158, D-4) — independent of
     * ownership, so a shared view may be favorited by many users at once.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'table_filter_view_favorites')->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'visibility' => FilterViewVisibility::class,
            'advanced_filters' => 'array',
            'rules' => 'array',
        ];
    }
}
