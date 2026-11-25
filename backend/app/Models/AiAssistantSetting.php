<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-row settings of the AI assistant (spec 0433). Audited through
 * LogsModelActivity (author and date of every change). It never holds the
 * OpenRouter key. Read and written only through AiAssistantSettingsService.
 */
#[Fillable([
    'enabled', 'model_id', 'instructions',
    'routing_zdr', 'routing_data_collection', 'routing_require_parameters', 'routing_sort',
    'spend_cap_person_usd', 'spend_cap_total_usd', 'updated_by_id',
])]
class AiAssistantSetting extends Model
{
    use LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'routing_zdr' => 'boolean',
            'routing_require_parameters' => 'boolean',
            'spend_cap_person_usd' => 'decimal:2',
            'spend_cap_total_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * The routing block in the OpenRouter `provider` parameter shape.
     *
     * @return array{zdr: bool, data_collection: string, require_parameters: bool, sort: string}
     */
    public function routing(): array
    {
        return [
            'zdr' => $this->routing_zdr,
            'data_collection' => $this->routing_data_collection,
            'require_parameters' => $this->routing_require_parameters,
            'sort' => $this->routing_sort,
        ];
    }
}
