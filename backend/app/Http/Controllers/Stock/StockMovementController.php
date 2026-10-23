<?php

namespace App\Http\Controllers\Stock;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Stock\ListStockMovementsRequest;
use App\Http\Requests\Stock\StoreStockMovementRequest;
use App\Http\Resources\StockBalanceResource;
use App\Http\Resources\StockMovementResource;
use App\Services\Stock\StockMovementService;
use App\Services\Stock\StockQueryService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Stock movements (spec 0307): record one and read the history of a row. The
 * register is append-only, so there is no update or delete.
 */
class StockMovementController extends BaseApiController
{
    public function __construct(
        private readonly StockMovementService $service,
        private readonly StockQueryService $queries,
    ) {}

    /**
     * GET /api/stock-movements
     */
    public function index(ListStockMovementsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            return $this->ok(StockMovementResource::collection($this->queries->history(
                (int) $filters['product_id'],
                (int) $filters['operational_site_id'],
                isset($filters['user_id']) ? (int) $filters['user_id'] : null,
            )));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/stock-movements
     */
    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        try {
            $result = $this->service->record($request->user(), $request->toData());

            return $this->ok([
                'movement' => new StockMovementResource($result['movement']),
                'balances' => StockBalanceResource::collection($result['balances']),
            ], __('stock.recorded'), HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
