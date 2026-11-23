<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One HTTP call to or from Formalab. Never holds credentials or tokens.
 */
#[Fillable([
    'direction', 'method', 'endpoint', 'request_summary', 'status_code', 'outcome',
    'duration_ms', 'trigger', 'error_message', 'response_summary',
])]
class FormalabCallLog extends Model
{
    use Prunable;

    public const string DIRECTION_INBOUND = 'inbound';

    public const string DIRECTION_OUTBOUND = 'outbound';

    public const string OUTCOME_SUCCESS = 'success';

    public const string OUTCOME_FAILURE = 'failure';

    protected $table = 'formalab_call_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_summary' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('formalab.log_retention_days')));
    }
}
