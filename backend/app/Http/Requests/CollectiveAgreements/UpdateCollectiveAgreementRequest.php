<?php

namespace App\Http\Requests\CollectiveAgreements;

use App\Models\CollectiveAgreement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/collective-agreements/{collectiveAgreement}
 * (spec 0272). Every field is `sometimes` to support partial updates; a level
 * `id` must belong to THIS agreement.
 */
class UpdateCollectiveAgreementRequest extends StoreCollectiveAgreementRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $agreement = $this->agreement();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('collective_agreements', 'name')->ignore($agreement->id)],
            ...$this->sharedRules(),
            'levels.*.id' => [
                'sometimes', 'nullable', 'integer', 'distinct',
                Rule::exists('collective_agreement_levels', 'id')->where('collective_agreement_id', $agreement->id),
            ],
        ];
    }

    protected function authorizationModel(): ?Model
    {
        return $this->agreement();
    }

    private function agreement(): CollectiveAgreement
    {
        /** @var CollectiveAgreement $agreement */
        $agreement = $this->route('collectiveAgreement');

        return $agreement;
    }
}
