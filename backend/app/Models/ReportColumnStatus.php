<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One working status counted by one status-based column of "Statistiche
 * Gestione Richieste" (spec 0391 D-4). `column_key` is a
 * App\Enums\ReportStatusColumn value; written only by
 * App\Services\RequestManagement\Report\ReportColumnStatusMap::replace().
 */
class ReportColumnStatus extends BaseModel
{
    /** @var list<string> */
    protected $fillable = ['column_key', 'quote_workflow_status_id'];

    /**
     * @return BelongsTo<QuoteWorkflowStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(QuoteWorkflowStatus::class, 'quote_workflow_status_id');
    }
}
