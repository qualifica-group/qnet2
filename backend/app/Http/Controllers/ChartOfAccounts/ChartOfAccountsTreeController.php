<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Services\ChartOfAccounts\ChartOfAccountsTreeBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/chart-of-accounts/tree (spec 0348, D-12): the whole chart in one
 * response. Gated on `chart-accounts.view`.
 */
class ChartOfAccountsTreeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ChartOfAccountsTreeBuilder $builder) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->authorize('chart-accounts.view');

            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->builder->build($actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
