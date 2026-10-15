<?php

namespace App\Http\Controllers\CommissionSettlements;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Resources\CommissionSettlementResource;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The detail envelope shared by the settlement controllers: the Resource with
 * the field permissions of the actor (settlement and movements) plus the
 * permissions block. For controllers extending BaseApiController.
 */
trait RespondsWithSettlement
{
    private function respond(User $actor, CommissionSettlement $settlement): JsonResponse
    {
        $authorization = app(AuthorizationRegistry::class);
        $builder = app(ResourcePermissionsBuilder::class);
        $permissions = $builder->build($authorization->resolve('commission-settlements'), $actor, $settlement);
        $movementFields = $builder->build($authorization->resolve('commission-accruals'), $actor, null)['fields'];

        return $this->okWithPermissions(
            new CommissionSettlementResource($settlement, $permissions['fields'], $movementFields),
            $permissions,
        );
    }
}
