<?php

namespace App\Services;

use App\DataObjects\AgentFiscalProfiles\AgentFiscalProfileData;
use App\Enums\HttpStatusEnum;
use App\Models\AgentFiscalProfile;
use App\Models\CommissionSettlement;
use App\Models\Referent;
use App\Models\User;
use App\Rules\NonOverlappingFiscalProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `agent-fiscal-profiles` resource (spec 0212). The
 * validity-overlap invariant (D-8) is checked by the FormRequest rule on the
 * normal path and re-checked here under a lock on the referent row, so two
 * concurrent writes cannot both pass; this Service also owns the creator stamp.
 */
class AgentFiscalProfileService
{
    /** Relations the AgentFiscalProfileResource reads. */
    private const array RESOURCE_RELATIONS = ['referent:id,name', 'vatRate:id,name,rate', 'paymentMethod:id,name', 'costType:id,name'];

    public function create(AgentFiscalProfileData $data, User $actor): AgentFiscalProfile
    {
        $profile = DB::transaction(function () use ($data, $actor): AgentFiscalProfile {
            $this->assertNoOverlap($data);

            return AgentFiscalProfile::create([...$data->toAttributes(), 'created_by' => $actor->id]);
        });

        return $this->detail($profile);
    }

    public function update(AgentFiscalProfile $profile, AgentFiscalProfileData $data): AgentFiscalProfile
    {
        DB::transaction(function () use ($profile, $data): void {
            $this->assertNoOverlap($data, $profile->id);

            $profile->fill($data->toAttributes())->save();
        });

        return $this->detail($profile);
    }

    /**
     * A profile referenced by a settlement (draft, issued or cancelled) is its
     * snapshot source and cannot be deleted (spec 0213, M-3): 409.
     */
    public function delete(AgentFiscalProfile $profile): void
    {
        if (CommissionSettlement::query()->where('agent_fiscal_profile_id', $profile->id)->exists()) {
            abort(HttpStatusEnum::CONFLICT->value, __('This fiscal profile is used by a commission settlement.'));
        }

        $profile->delete();
    }

    /**
     * The profile with the relations its Resource needs eager-loaded.
     */
    public function detail(AgentFiscalProfile $profile): AgentFiscalProfile
    {
        return $profile->fresh(self::RESOURCE_RELATIONS);
    }

    /**
     * Serializes concurrent writers per referent (lock on its row) and refuses
     * an overlapping validity range with the same 422 as the FormRequest rule.
     */
    private function assertNoOverlap(AgentFiscalProfileData $data, ?int $ignoreProfileId = null): void
    {
        Referent::query()->whereKey($data->referentId)->lockForUpdate()->first();

        if (NonOverlappingFiscalProfile::overlaps($data->referentId, $data->validFrom, $data->validUntil, $ignoreProfileId)) {
            throw ValidationException::withMessages(['valid_from' => __('agent_fiscal_profiles.overlapping_validity')]);
        }
    }
}
