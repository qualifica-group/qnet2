<?php

namespace App\Models;

use App\Enums\QuoteStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quote status lookup entity (spec 0065, D-2): a plain clone of
 * OpportunityStatus — a persisted status configurator describing a Quote's
 * working state. `name` is unique; `system_key` (nullable, the THREE
 * mandatory "Bozza"/"Accettata"/"Rifiutata" rows) is DELIBERATELY absent from
 * #[Fillable] — never mass-assignable, written only by the create migration
 * and App\Services\Statuses\SystemStatusGuard/StatusOrderManager.
 * `sort_order` stays fillable: server-managed (StatusOrderManager
 * places/reorders it), not user-fillable at the FormRequest layer. `group` is
 * the fixed classification (App\Enums\QuoteStatusGroup — module-specific: the
 * terminal phase is split into closed_won/closed_lost). D-2 explicitly
 * excludes any workflow configurator equivalent to `opportunity_workflows`.
 */
#[Fillable(['name', 'color', 'sort_order', 'group'])]
class QuoteStatus extends BaseModel
{
    /** @use HasFactory<QuoteStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system row pinned to sort_order=0 (StatusOrderManager::reorder()).
     */
    public const StatusSystemKey SYSTEM_HEAD_KEY = StatusSystemKey::New;

    /**
     * The system rows that pin to the tail of the sort_order sequence
     * (StatusOrderManager), in the order they must appear: "Accettata" then
     * "Rifiutata" — the latter is ALWAYS last (D-2, mirroring
     * OpportunityStatus::SYSTEM_TAIL_KEYS).
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
            'group' => QuoteStatusGroup::class,
        ];
    }

    /**
     * The quotes classified under this status. `quote_status_id` is
     * restrictOnDelete (migration): deleting a status referenced by a quote
     * is rejected at the schema layer too (AC-014).
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Whether this is one of the three mandatory system rows ("Bozza"/
     * "Accettata"/"Rifiutata", spec 0065 D-2) rather than a custom,
     * user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
