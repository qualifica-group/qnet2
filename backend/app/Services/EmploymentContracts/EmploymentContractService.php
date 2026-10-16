<?php

namespace App\Services\EmploymentContracts;

use App\Enums\HttpStatusEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Append-only history of a person's contractual conditions (spec 0272 D-1).
 *
 * The condition in force (latest `starts_on` not after today) is mirrored on
 * `employment_profiles.relationship_type`/`company_id` (D-2): this service and
 * the daily job (`employment:daily-sync`) are the ONLY writers of that copy.
 */
class EmploymentContractService
{
    /**
     * The profile's conditions, latest start first, annotated with the derived
     * `ends_on` (day before the next condition starts) and `is_current`.
     *
     * @return Collection<int, EmploymentContract>
     */
    public function history(EmploymentProfile $profile): Collection
    {
        $contracts = $profile->contracts()
            ->with(['company', 'collectiveAgreementLevel.agreement'])
            ->get();

        $today = today()->toDateString();
        $currentId = $this->currentOf($contracts, $today)?->id;
        $nextStart = null;

        // Walking latest -> earliest: the previously visited row is the next one in time.
        foreach ($contracts as $contract) {
            $contract->setAttribute('ends_on', $nextStart?->copy()->subDay()->toDateString());
            $contract->setAttribute('is_current', $contract->id === $currentId);
            $nextStart = $contract->starts_on;
        }

        return $contracts;
    }

    /**
     * @param  array<string, mixed>  $data  validated payload (no profile id)
     */
    public function create(EmploymentProfile $profile, array $data): EmploymentContract
    {
        // Step 1: persist the condition and realign the profile copy atomically
        $contract = DB::transaction(function () use ($profile, $data): EmploymentContract {
            $contract = $profile->contracts()->create($data);
            $this->syncCurrent($profile);

            return $contract;
        });

        // Step 2: return it with the derived timeline fields
        return $this->history($profile)->firstWhere('id', $contract->id);
    }

    /**
     * Only the latest condition (by `starts_on`) may be removed (D-1).
     */
    public function delete(EmploymentContract $contract): void
    {
        $profile = $contract->profile;

        DB::transaction(function () use ($contract, $profile): void {
            $hasLater = $profile->contracts()->whereDate('starts_on', '>', $contract->starts_on)->exists();

            if ($hasLater) {
                abort(HttpStatusEnum::CONFLICT->value, __('employment_contracts.only_latest_deletable'));
            }

            $contract->delete();
            $this->syncCurrent($profile);
        });
    }

    /**
     * Mirror the condition in force on the profile (null when none has started).
     */
    public function syncCurrent(EmploymentProfile $profile): void
    {
        $current = $profile->contracts()
            ->whereDate('starts_on', '<=', today()->toDateString())
            ->first();

        $profile->update([
            'relationship_type' => $current?->relationship_type,
            'company_id' => $current?->company_id,
        ]);
    }

    /**
     * First condition coming from the legacy import (spec 0272 D-9): created
     * when the profile has none, refreshed when the one at that date exists,
     * left alone otherwise (never a duplicate on re-import). Returns whether
     * anything was written.
     */
    public function upsertInitial(EmploymentProfile $profile, string $startsOn, RelationshipTypeEnum $relationshipType, ?int $companyId): bool
    {
        return DB::transaction(function () use ($profile, $startsOn, $relationshipType, $companyId): bool {
            $existing = $profile->contracts()->whereDate('starts_on', $startsOn)->first();

            if ($existing === null && $profile->contracts()->exists()) {
                return false;
            }

            $attributes = ['relationship_type' => $relationshipType, 'company_id' => $companyId];

            if ($existing === null) {
                $profile->contracts()->create($attributes + ['starts_on' => $startsOn]);
            } elseif ($existing->fill($attributes)->isDirty()) {
                $existing->save();
            } else {
                return false;
            }

            $this->syncCurrent($profile);

            return true;
        });
    }

    /**
     * @param  Collection<int, EmploymentContract>  $contracts  ordered latest start first
     */
    private function currentOf(Collection $contracts, string $today): ?EmploymentContract
    {
        return $contracts->first(fn (EmploymentContract $contract): bool => $contract->starts_on->toDateString() <= $today);
    }
}
