<?php

declare(strict_types=1);

namespace App\Http\Requests\Rewards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates `PATCH /api/rewards/{reward}` (spec 0060 §4, D-1: the card's
 * inline status edit — the only mutable surface on `rewards` outside the
 * Opportunity/Gestione Richiesta chip payload). `reward_status_id` must
 * reference an EXISTING AND ACTIVE row (D-7/BR-8): a deactivated status
 * stays visible on rewards already assigned to it, but is never
 * (re-)selectable, so the `exists` rule is scoped with a `where` clause
 * rather than a plain `exists:reward_statuses,id`.
 *
 * Authorization is intentionally NOT handled here: the controller gates the
 * whole endpoint on `rewarded-referents.update` directly (D-8 — no dedicated
 * `reward_status` field-permission, see RewardController::updateStatus).
 */
class UpdateRewardStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reward_status_id' => [
                'required',
                'integer',
                Rule::exists('reward_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
        ];
    }

    public function rewardStatusId(): int
    {
        return (int) $this->validated('reward_status_id');
    }
}
