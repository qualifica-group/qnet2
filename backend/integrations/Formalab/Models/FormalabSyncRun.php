<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One execution of the catalogue sync (or of a webhook/link refresh).
 */
#[Fillable(['trigger', 'outcome', 'started_at', 'finished_at', 'classes_synced', 'work_orders_updated', 'classes_removed', 'error_message', 'warning_message'])]
class FormalabSyncRun extends Model
{
    use Prunable;

    public const string OUTCOME_RUNNING = 'running';

    public const string OUTCOME_SUCCESS = 'success';

    public const string OUTCOME_FAILURE = 'failure';

    protected $table = 'formalab_sync_runs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'classes_synced' => 'integer',
            'work_orders_updated' => 'integer',
            'classes_removed' => 'integer',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays((int) config('formalab.log_retention_days')));
    }
}
