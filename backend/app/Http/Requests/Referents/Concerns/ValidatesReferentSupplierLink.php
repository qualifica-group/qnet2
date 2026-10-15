<?php

namespace App\Http\Requests\Referents\Concerns;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\Referent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * The referent -> supplier registry link (spec 0267, D-7): the target must be
 * a supplier registry, and once an approved settlement exists the link can no
 * longer be moved to a different registry (the settlement's purchase invoice
 * already points at the old supplier).
 */
trait ValidatesReferentSupplierLink
{
    /**
     * @return array<int, mixed>
     */
    protected function supplierRegistryRules(): array
    {
        return [
            'nullable', 'integer',
            Rule::exists('registries', 'id')->where('is_supplier', true),
        ];
    }

    /**
     * Refuses a change of `supplier_registry_id` while the referent has an
     * APPROVED settlement. Re-sending the current value is a no-op.
     */
    protected function validateSupplierLinkNotLocked(Validator $validator, ?Referent $referent): void
    {
        if ($referent === null || ! $this->has('supplier_registry_id') || $validator->errors()->has('supplier_registry_id')) {
            return;
        }

        $submitted = $this->input('supplier_registry_id');
        $submitted = $submitted === null || $submitted === '' ? null : (int) $submitted;

        if ($submitted === $referent->supplier_registry_id) {
            return;
        }

        $locked = CommissionSettlement::query()
            ->where('referent_id', $referent->id)
            ->where('status', CommissionSettlementStatus::Approved)
            ->exists();

        if ($locked) {
            $validator->errors()->add('supplier_registry_id', __('referents.supplier_link_locked'));
        }
    }
}
