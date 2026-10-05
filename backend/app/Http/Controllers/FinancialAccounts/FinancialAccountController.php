<?php

namespace App\Http\Controllers\FinancialAccounts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\FinancialAccounts\StoreFinancialAccountRequest;
use App\Http\Requests\FinancialAccounts\UpdateFinancialAccountRequest;
use App\Http\Resources\FinancialAccountResource;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\FinancialAccountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `financial-accounts` resource (spec 0189) plus the
 * audited card-number reveal. Thin controller: FormRequest validation,
 * server-side authorization (FinancialAccountPolicy), Service call, response.
 *
 * @see FinancialAccountService
 */
class FinancialAccountController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FinancialAccountService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/financial-accounts/{financialAccount}
     */
    public function show(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $this->authorize('view', $financialAccount);

            $account = $this->service->detail($financialAccount);

            return $this->okWithPermissions(
                new FinancialAccountResource($account),
                $this->buildPermissions($request->user(), $account),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /**
     * POST /api/financial-accounts
     */
    public function store(StoreFinancialAccountRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', FinancialAccount::class);

            $account = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new FinancialAccountResource($account),
                $this->buildPermissions($request->user(), $account),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/financial-accounts/{financialAccount}
     */
    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $this->authorize('update', $financialAccount);

            $account = $this->service->update($financialAccount, $request->toData());

            return $this->okWithPermissions(
                new FinancialAccountResource($account),
                $this->buildPermissions($request->user(), $account),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /**
     * DELETE /api/financial-accounts/{financialAccount} (409 when the bank
     * account still has cards associated).
     */
    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $this->authorize('delete', $financialAccount);

            $this->service->delete($financialAccount);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /**
     * GET /api/financial-accounts/{financialAccount}/card-number — the clear
     * card number, audited (the only place it ever leaves the server).
     */
    public function revealCardNumber(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $this->authorize('revealCardNumber', $financialAccount);

            return $this->ok(['card_number' => $this->service->revealCardNumber($financialAccount, $request->user())]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, FinancialAccount $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('financial-accounts'), $actor, $model);
    }
}
