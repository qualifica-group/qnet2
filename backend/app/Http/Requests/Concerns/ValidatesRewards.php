<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the `rewards` payload of the opportunity write
 * endpoints (spec 0059, `<sync_semantics>`/D-3): a to-many collection of
 * `{reward_type_id}` rows, sparse like `products_of_interest` — key absent
 * means untouched, `[]` clears every assignment (RewardAssignmentWriter owns
 * the sync). The base per-row rules live in rewardsRules(); the two D-3
 * cross-field invariants run in validateRewards(), called from each
 * request's own withValidator():
 *   - a non-empty `rewards` requires the opportunity to HAVE a reporter
 *     (Segnalatore) once this request is applied (AC-021, first half);
 *   - `reporter_id` may not be cleared while the opportunity already carries
 *     reward rows (AC-021, second half) — no silent orphans.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesRewards
{
    /**
     * @param  bool  $required  false everywhere today (create AND update):
     *                          unlike `product_lines`, an opportunity is
     *                          allowed to carry zero rewards.
     * @return array<string, array<int, mixed>>
     */
    protected function rewardsRules(bool $required = false): array
    {
        return [
            'rewards' => $required
                ? ['required', 'array', 'max:20']
                : ['sometimes', 'array', 'max:20'],
            'rewards.*.reward_type_id' => ['required', 'integer', Rule::exists('reward_types', 'id'), 'distinct'],
        ];
    }

    /**
     * @param  ?Opportunity  $opportunity  null on create — there is nothing
     *                                     PERSISTED yet, so the "existing
     *                                     rewards" guard never applies
     */
    protected function validateRewards(Validator $validator, ?Opportunity $opportunity): void
    {
        $rewards = $this->input('rewards');
        $reporterSubmitted = $this->has('reporter_id');
        $resultingReporterId = $reporterSubmitted ? $this->input('reporter_id') : $opportunity?->reporter_id;

        if (is_array($rewards) && $rewards !== [] && $resultingReporterId === null) {
            $validator->errors()->add('rewards', 'Rewards require the opportunity to have a reporter (Segnalatore).');
        }

        if ($reporterSubmitted && $resultingReporterId === null && $opportunity !== null && $opportunity->rewards()->exists()) {
            $validator->errors()->add('reporter_id', 'The reporter cannot be cleared while rewards are assigned to this opportunity.');
        }
    }
}
