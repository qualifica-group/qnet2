<?php

namespace App\Http\Controllers\AbsenceBalances;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceBalances\UpsertAbsenceAllowanceRequest;
use App\Models\AbsenceAllowance;
use App\Models\AbsenceType;
use App\Models\User;
use App\Services\Absences\Balances\AbsenceBalanceBuilder;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * PUT /api/absence-allowances (spec 0278 D-1): upsert on (person, type, year),
 * answering with the refreshed balance row.
 */
class AbsenceAllowanceController extends BaseApiController
{
    public function __construct(private readonly AbsenceBalanceBuilder $balances) {}

    public function upsert(UpsertAbsenceAllowanceRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Step 1: upsert through the model so the activity log records it
            $allowance = AbsenceAllowance::query()->firstOrNew([
                'user_id' => $data['user_id'],
                'absence_type_id' => $data['absence_type_id'],
                'year' => $data['year'],
            ]);
            $allowance->fill([
                'annual_minutes' => $data['annual_minutes'],
                'carried_over_minutes' => $data['carried_over_minutes'],
            ])->save();

            // Step 2: answer with the balance row
            $row = $this->balances->row(
                User::query()->findOrFail($data['user_id']),
                AbsenceType::query()->findOrFail($data['absence_type_id']),
                (int) $data['year'],
            );

            return $this->ok($row);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
