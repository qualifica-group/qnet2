<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Audit trail of a registry/referent merge (spec 0394, D-11): one row per
 * merged-away duplicate. Written only by App\Services\Merge\RecordMergeService.
 */
#[Fillable(['entity_type', 'survivor_id', 'merged_id', 'merged_old_id', 'merged_name', 'snapshot', 'merged_by'])]
class RecordMerge extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'survivor_id' => 'integer',
            'merged_id' => 'integer',
            'merged_old_id' => 'integer',
        ];
    }
}
