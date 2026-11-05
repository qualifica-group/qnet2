<?php

declare(strict_types=1);

namespace App\Http\Controllers\Treasury;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\OpeningBalanceRequest;
use App\Http\Resources\FinancialAccountResource;
use App\Models\FinancialAccount;
use App\Services\FinancialAccountService;
use App\Services\Treasury\AccountStatementOpeningBalanceUpdater;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/** PATCH /api/financial-accounts/{financialAccount}/opening-balance (spec 0360, D-1). */
class OpeningBalanceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AccountStatementOpeningBalanceUpdater $updater,
        private readonly FinancialAccountService $accounts,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(OpeningBalanceRequest $request, FinancialAccount $financialAccount): JsonResponse
    {
        try {
            $this->authorize('manageOpeningBalance', $financialAccount);

            $data = $request->validated();
            $account = $this->accounts->detail($this->updater->handle($financialAccount, (string) $data['opening_balance'], $data['opening_balance_date']));

            return $this->okWithPermissions(
                new FinancialAccountResource($account),
                $this->permissionsBuilder->build($this->authorization->resolve('financial-accounts'), $request->user(), $account),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['financialAccount' => $financialAccount->id]);
        }
    }
}
