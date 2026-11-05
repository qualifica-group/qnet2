<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes of the manual treasury movements (spec 0360). Validation lives in the
 * requests; every change is audited by the model's activity log (D-8).
 */
final class TreasuryMovementService
{
    /** Relations the resource reads, eager loaded to keep lazy loading off. */
    public const array RELATIONS = [
        'account.company:id,denomination',
        'counterpartAccount.company:id,denomination',
        'costType:id,code,name',
        'revenueType:id,code,name',
        'treasuryType:id,code,name',
        'registry:id,name',
        'workOrder:id,code',
        'creator:id,name',
    ];

    /**
     * @param  array<string, mixed>  $data  validated payload
     */
    public function create(array $data, User $actor): TreasuryMovement
    {
        // Step 1: persist the movement with its author
        $movement = DB::transaction(
            fn (): TreasuryMovement => TreasuryMovement::query()->create([...$data, 'created_by' => $actor->id]),
        );

        // Step 2: reload with the resource relations
        return $this->detail($movement);
    }

    /**
     * @param  array<string, mixed>  $data  validated payload
     */
    public function update(TreasuryMovement $movement, array $data): TreasuryMovement
    {
        // Step 1: refuse a reconciled movement, then apply the changes (the activity log records before/after)
        DB::transaction(function () use ($movement, $data): bool {
            ReconciliationGuard::assertMovementFree($this->locked($movement));

            return $movement->update($data);
        });

        // Step 2: reload with the resource relations
        return $this->detail($movement);
    }

    public function delete(TreasuryMovement $movement): void
    {
        DB::transaction(function () use ($movement): ?bool {
            ReconciliationGuard::assertMovementFree($this->locked($movement));

            return $movement->delete();
        });
    }

    /** Fresh read under lock: a tick made meanwhile must not be lost. */
    private function locked(TreasuryMovement $movement): TreasuryMovement
    {
        return TreasuryMovement::query()->whereKey($movement->getKey())->lockForUpdate()->firstOrFail();
    }

    public function detail(TreasuryMovement $movement): TreasuryMovement
    {
        return $movement->load(self::RELATIONS)->loadCount('attachments');
    }
}
