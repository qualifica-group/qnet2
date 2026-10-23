<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Stock\ListStockBalancesRequest;
use App\Http\Resources\StockBalanceResource;
use App\Services\Stock\StockQueryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Read of the stock balances (spec 0307): the product and user sections.
 */
class StockBalanceController extends BaseApiController
{
    public function __construct(private readonly StockQueryService $queries) {}

    /**
     * GET /api/stock-balances
     */
    public function index(ListStockBalancesRequest $request): JsonResponse
    {
        try {
            return $this->ok(StockBalanceResource::collection($this->queries->balances($request->validated())));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
