<?php

namespace App\Models;

use App\Enums\StatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OpportunityStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opportunity status lookup entity (spec 0043): a persisted status
 * configurator describing an Opportunity's working
 * state. `name` is unique (BR-3); `system_key` (nullable, the THREE
 * mandatory "Nuova"/"Chiusa con successo"/"Persa" rows — D-2) is
 * DELIBERATELY absent from #[Fillable] — never mass-assignable, written only
 * by the create migration and App\Services\Statuses\SystemStatusGuard/
 * StatusOrderManager. `sort_order` stays fillable: server-managed
 * (StatusOrderManager places/reorders it), not user-fillable at the
 * FormRequest layer. `group` is the fixed 3-value classification
 * (App\Enums\StatusGroup).
 */
#[Fillable(['name', 'color', 'sort_order', 'group'])]
class OpportunityStatus extends BaseModel
{
    /** @use HasFactory<OpportunityStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system rows pinned to the head of the sort_order sequence
     * (StatusOrderManager::reorder(), spec 0039 D-5): a single row here,
     * "Nuova" at 0 — the array shape exists because App\Models\RewardStatus
     * carries TWO head rows (spec 0073, D-6).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [StatusSystemKey::New];

    /**
     * The system rows that pin to the tail of the sort_order sequence
     * (StatusOrderManager, spec 0039 D-5), in the order they must appear:
     * "Chiusa con successo" then "Persa" — the latter is ALWAYS last (D-2).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [StatusSystemKey::Won, StatusSystemKey::Lost];

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
     * The opportunities classified under this status. `opportunity_status_id`
     * is restrictOnDelete (migration, BR-2): deleting a status referenced by
     * an opportunity is rejected at the schema layer too.
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Whether this is one of the three mandatory system rows ("Nuova"/
     * "Chiusa con successo"/"Persa", spec 0043 D-2) rather than a custom,
     * user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
