<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A navigation item (config `key`) assigned to a workspace (spec 0317).
 */
#[Fillable(['workspace_id', 'navigation_key'])]
class WorkspaceNavigationItem extends Model
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
