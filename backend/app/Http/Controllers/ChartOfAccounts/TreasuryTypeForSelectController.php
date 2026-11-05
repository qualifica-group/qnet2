<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\DocumentForSelectRequest;
use App\Http\Resources\TreasuryTypeForSelectResource;
use App\Services\ChartOfAccounts\TreasuryTypeForSelectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/treasury-types/for-select (spec 0360, D-3): the financial sub-accounts
 * a user can pick on treasury movements. Allowed to who can view the
 * chart's financial sub-accounts or the movements, since the picker lives there.
 */
class TreasuryTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly TreasuryTypeForSelectService $service) {}

    public function __invoke(DocumentForSelectRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            Gate::allowIf($actor->can('treasury-types.view') || $actor->can('treasury-movements.view'));

            $result = $this->service->forSelect($request->toData());

            return $this->ok([
                'items' => TreasuryTypeForSelectResource::collection($result->items)->resolve($request),
                'total' => $result->total,
                'offset' => $result->offset,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
