<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * The registry's "Referente principale" must be one of the registry's
 * referents (spec 0330 INV-1). Shared by the store and update requests; the
 * set to check against is the one submitted in the same request, or the
 * already linked one when the request does not touch the list.
 */
trait ValidatesPrimaryReferent
{
    /**
     * Referent ids the registry will have once this request is applied.
     *
     * @return array<int, int>
     */
    abstract protected function resultingReferentIds(): array;

    protected function validatePrimaryReferent(Validator $validator): void
    {
        $primaryId = $this->input('primary_referent_id');

        if ($primaryId === null || $validator->errors()->has('primary_referent_id') || $validator->errors()->has('referent_ids.*')) {
            return;
        }

        if (! in_array((int) $primaryId, $this->resultingReferentIds(), true)) {
            $validator->errors()->add('primary_referent_id', __('registries.primary_referent_not_linked'));
        }
    }

    /**
     * @return array<int, int>
     */
    protected function submittedReferentIds(): array
    {
        return array_map('intval', (array) $this->input('referent_ids', []));
    }
}
