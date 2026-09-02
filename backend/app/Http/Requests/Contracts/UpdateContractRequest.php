<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\DataObjects\Contracts\UpdateContractData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Contract;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/contracts/{contract} (spec 0072,
 * MT-02). Every field is `sometimes` (partial PATCH). `contract_status_id`,
 * when submitted, must reference an ACTIVE status — the `restrictOnDelete`
 * FK only guarantees existence, not activeness (data_contract: "422 stato
 * inesistente/non attivo").
 *
 * `renewal_date` <= `expiry_date` (spec 0095, D-1): this PATCH is now the
 * ONLY surviving writer of both fields after the retired `schedule` endpoint
 * (which enforced the same rule via `before_or_equal:expiry_date`) was
 * removed — the constraint has to live here now, not just on a route that no
 * longer exists. `assertRenewalNotAfterExpiry()` below compares against the
 * MODEL's current value for whichever of the two fields this partial PATCH
 * does not submit (the old endpoint always received both together, so it
 * never needed a fallback).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $contract)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $contract (AC-039).
 */
class UpdateContractRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ContractPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contract_status_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('contract_statuses', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'renewal_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'payment_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'comments' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->assertRenewalNotAfterExpiry($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'contracts';
    }

    protected function authorizationModel(): ?Model
    {
        return $this->currentContract();
    }

    private function currentContract(): Contract
    {
        /** @var Contract $contract */
        $contract = $this->route('contract');

        return $contract;
    }

    /**
     * A renewal later than the expiry makes no business sense (the retired
     * `schedule` endpoint's own rule, spec 0072) — restored here explicitly
     * for all 4 shapes this PARTIAL PATCH can take:
     *  (a) both submitted -> compared against each other;
     *  (b) only `renewal_date` submitted -> compared against the MODEL's
     *      current `expiry_date`;
     *  (c) only `expiry_date` submitted -> compared against the MODEL's
     *      current `renewal_date`. DECISION (explicit, not incidental):
     *      bringing the expiry forward so it now falls BEFORE an
     *      already-saved renewal is REJECTED (422) — an inconsistent pair
     *      is never persisted, the same invariant as (a)/(b), even though
     *      only `expiry_date` was submitted this time;
     *  (d) either side resolves to null (never set, or explicitly cleared
     *      by this same PATCH) -> nothing to compare, no error — this PATCH
     *      does not invent an obligation for the other field that did not
     *      exist before.
     */
    private function assertRenewalNotAfterExpiry(Validator $validator): void
    {
        $expiryDate = $this->resolvedDate('expiry_date', $this->currentContract()->expiry_date);
        $renewalDate = $this->resolvedDate('renewal_date', $this->currentContract()->renewal_date);

        if ($expiryDate === null || $renewalDate === null) {
            return;
        }

        if ($renewalDate->gt($expiryDate)) {
            $validator->errors()->add('renewal_date', 'The renewal date must be before or equal to the expiry date.');
        }
    }

    /**
     * The effective value of a `sometimes` date field: the submitted one
     * (parsed, or null when malformed — its own `date` rule already flags
     * that separately) when the key is present, otherwise `$fallback`.
     */
    private function resolvedDate(string $key, ?Carbon $fallback): ?Carbon
    {
        if (! $this->has($key)) {
            return $fallback;
        }

        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateContractData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateContractData::fromValidated($validated);
    }
}
