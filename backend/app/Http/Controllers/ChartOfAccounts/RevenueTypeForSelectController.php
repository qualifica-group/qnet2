<?php

namespace App\Http\Controllers\ChartOfAccounts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Shared\DocumentForSelectRequest;
use App\Http\Resources\RevenueTypeForSelectResource;
use App\Services\ChartOfAccounts\RevenueTypeForSelectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/revenue-types/for-select (spec 0350, D-10): the revenue sub-accounts
 * a user can pick on products, categories and invoice lines. Allowed to who
 * can view the chart's revenue sub-accounts, the invoices or the products,
 * since the picker lives on those forms.
 */
class RevenueTypeForSelectController extends BaseApiController
{
    public function __construct(private readonly RevenueTypeForSelectService $service) {}

    public function __invoke(DocumentForSelectRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            Gate::allowIf($actor->can('revenue-types.view') || $actor->can('invoices.view') || $actor->can('products.view'));

            $result = $this->service->forSelect($request->toData());

            return $this->ok([
                'items' => RevenueTypeForSelectResource::collection($result->items)->resolve($request),
                'total' => $result->total,
                'offset' => $result->offset,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
