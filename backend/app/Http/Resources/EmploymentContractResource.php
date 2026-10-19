<?php

namespace App\Http\Resources;

use App\Models\EmploymentContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One contractual condition (spec 0272). Expects the `ends_on`/`is_current`
 * attributes set by EmploymentContractService::history() and the `company` and
 * `collectiveAgreementLevel.agreement` relations loaded. `salary_*` are
 * emitted only with `users.manageCompensation` (D-5): the model hides them, so
 * they appear here and nowhere else.
 *
 * @mixin EmploymentContract
 */
class EmploymentContractResource extends JsonResource
{
    private const string COMPENSATION_PERMISSION = 'users.manageCompensation';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCompensation = (bool) $request->user()?->can(self::COMPENSATION_PERMISSION);
        $level = $this->collectiveAgreementLevel;

        return [
            'id' => $this->id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->getAttribute('ends_on'),
            'is_current' => (bool) $this->getAttribute('is_current'),
            'relationship_type' => $this->relationship_type,
            'company' => $this->company === null
                ? null
                : ['id' => $this->company->id, 'label' => $this->company->denomination],
            'collective_agreement_level' => $level === null
                ? null
                : ['id' => $level->id, 'label' => "{$level->agreement->name} – {$level->code}"],
            $this->mergeWhen($canSeeCompensation, fn (): array => [
                'salary_amount' => $this->salary_amount,
                'salary_period' => $this->salary_period,
            ]),
            'change_reason' => $this->change_reason,
            'created_at' => $this->created_at,
        ];
    }
}
