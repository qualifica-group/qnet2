<?php

namespace App\Models;

use App\Enums\StatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PipelineStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project status lookup entity (spec 0023): a full-CRUD classification
 * (name/color/sort_order) shared by Projects and Campaigns. Named
 * `PipelineStatus`/`pipeline_statuses` (not `State`/`states`) because that name
 * is already taken by the geo entity ("Regione").
 *
 * spec 0039: `system_key` (nullable, the two mandatory "Nuovo"/"Chiuso" rows)
 * is DELIBERATELY absent from #[Fillable] — never mass-assignable, written
 * only by the system-status migration and App\Services\Statuses\
 * SystemStatusGuard/StatusOrderManager. `sort_order` stays fillable (D-5):
 * it becomes server-managed (StatusOrderManager places/reorders it), not
 * user-fillable at the FormRequest layer, but the Service still assigns it
 * via mass-assignment internally. `group` (pivot) is the fixed 3-value
 * classification (App\Enums\StatusGroup) — replaces the earlier "status
 * groups" lookup FK.
 */
#[Fillable(['name', 'color', 'sort_order', 'group'])]
class PipelineStatus extends BaseModel
{
    /** @use HasFactory<PipelineStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system rows pinned to the head of the sort_order sequence
     * (StatusOrderManager::reorder(), spec 0039 D-5): a single row here, so
     * "Nuovo" sits at 0 — the array shape mirrors SYSTEM_TAIL_KEYS, which
     * every status model shares (StatusOrderManager is generic over both).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [StatusSystemKey::New];

    /**
     * The system rows that pin to the tail of the sort_order sequence
     * (StatusOrderManager, spec 0039 D-5): just "Chiuso" here.
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [StatusSystemKey::Closed];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'group' => StatusGroup::class,
        ];
    }

    /**
     * The projects classified under this status. `pipeline_status_id` is
     * restrictOnDelete (migration, BR-4): deleting a status referenced by a
     * project is rejected at the schema layer too.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The standalone campaigns classified under this status (own, not
     * derived from a linked project — BR-2). Same restrictOnDelete guard.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Whether this is one of the two mandatory system rows ("Nuovo"/
     * "Chiuso", spec 0039 D-2) rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
