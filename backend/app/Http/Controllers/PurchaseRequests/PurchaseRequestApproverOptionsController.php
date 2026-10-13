<?php

namespace App\Http\Controllers\PurchaseRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PurchaseRequests\PurchaseRequestApproverOptionsRequest;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestApproverResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Eligible approvers of a purchase request for a requester and a business
 * function (spec 0245, D-5), computed by the same resolver the write
 * validation uses. Authorization lives in the FormRequest.
 */
class PurchaseRequestApproverOptionsController extends BaseApiController
{
    public function __construct(private readonly PurchaseRequestApproverResolver $resolver) {}

    /**
     * GET /api/purchase-requests/approver-options
     */
    public function __invoke(PurchaseRequestApproverOptionsRequest $request): JsonResponse
    {
        try {
            $functionId = $request->businessFunctionId();

            $result = $this->resolver->resolve(
                User::query()->findOrFail($request->requesterId()),
                $functionId === null ? null : BusinessFunction::query()->find($functionId),
            );

            return $this->ok([
                'source' => $result['source'],
                'approvers' => $result['approvers']
                    ->map(static fn (User $approver): array => ['id' => $approver->id, 'name' => $approver->name])
                    ->values()
                    ->all(),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
