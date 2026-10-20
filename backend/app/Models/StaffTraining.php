<?php

namespace App\Models;

use App\Enums\StaffTrainingOutcome;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\StaffTrainingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff course taken by a person (spec 0284 D-3). `expires_on` is a
 * snapshot of completion + course validity at save time. The single optional
 * certificate is served by dedicated endpoints, never by the generic
 * attachments API.
 */
#[Fillable(['user_id', 'staff_course_id', 'completed_on', 'expires_on', 'outcome', 'notes', 'recorded_by'])]
class StaffTraining extends BaseModel
{
    /** @use HasFactory<StaffTrainingFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'completed_on' => 'date:Y-m-d',
            'expires_on' => 'date:Y-m-d',
            'outcome' => StaffTrainingOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StaffCourse, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(StaffCourse::class, 'staff_course_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
