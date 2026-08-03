<?php

namespace App\Models;

use App\Enums\ContractStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ContractStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contract status lookup entity (spec 0072): a persisted status configurator
 * describing a Contract's working state (`contracts.contract_status_id`).
 * Combines RewardStatus' `description`/`is_active` addition with
 * DocumentLayout's exclusive `is_default` (BR-5, same invariant as
 * `DocumentLayoutDefaultManager`) and QuoteStatus' `group` classification
 * (`App\Enums\ContractStatusGroup` — a module-specific enum, D-5, not a reuse
 * of `QuoteStatusGroup`). `name` is unique; `system_key` (nullable, the FOUR
 * mandatory rows of D-2 — "Da validare"/"Sospeso"/"Annullato"/"Disdetto") is
 * DELIBERATELY absent from #[Fillable] — never mass-assignable, written only
 * by the create migration and
 * App\Services\Statuses\SystemStatusGuard/StatusOrderManager. `sort_order`
 * stays fillable: server-managed (StatusOrderManager places/reorders it), not
 * user-fillable at the FormRequest layer. D-2's conclusion follows from
 * this: "Da programmare"/"Programmato"/"In scadenza" are plain, deletable
 * custom rows, so no domain action can resolve them by system_key — "Valida"/
 * "Programma" take their destination status from the client instead.
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'is_active', 'is_default', 'group'])]
class ContractStatus extends BaseModel
{
    /** @use HasFactory<ContractStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The system rows pinned to the head of the sort_order sequence
     * (StatusOrderManager::reorder()): a single row here, "Da validare" at 0
     * — the array shape exists because App\Models\RewardStatus carries TWO
     * head rows (spec 0073, D-6).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_HEAD_KEYS = [StatusSystemKey::New];

    /**
     * The system rows that pin to the tail of the sort_order sequence
     * (StatusOrderManager), in the order they must appear: "Sospeso" then
     * "Annullato" then "Disdetto" (spec 0072 D-2).
     *
     * @var array<int, StatusSystemKey>
     */
    public const array SYSTEM_TAIL_KEYS = [StatusSystemKey::Suspended, StatusSystemKey::Cancelled, StatusSystemKey::Terminated];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'is_default' => 'bool',
            'group' => ContractStatusGroup::class,
        ];
    }

    /**
     * The contracts classified under this status. `contract_status_id` is
     * restrictOnDelete (migration): deleting a status referenced by a
     * contract is rejected at the schema layer too (AC-022).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Whether this is one of the four mandatory system rows ("Da validare"/
     * "Sospeso"/"Annullato"/"Disdetto", spec 0072 D-2) rather than a custom,
     * user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
