<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TimeEntryDayNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Day note (spec 0122, D-4): one free-text note per user per date, separate
 * from the individual segnatempo rows of that day. `unique(user_id, date)`
 * at the DB level backs the upsert-by-user+date PUT /api/time-entries/
 * day-notes relies on.
 */
#[Fillable(['user_id', 'date', 'note'])]
class TimeEntryDayNote extends BaseModel
{
    /** @use HasFactory<TimeEntryDayNoteFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
