<?php

namespace App\Http\Controllers\Registries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Registries\RegistryLedgerRequest;
use App\Models\Registry;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/registries/{registry}/ledger (spec 0266). Thin: the request
 * authorizes and validates, the service computes.
 */
class RegistryLedgerController extends BaseApiController
{
    public function __construct(private readonly LedgerService $service) {}

    public function __invoke(RegistryLedgerRequest $request, Registry $registry): JsonResponse
    {
        try {
            return $this->ok($this->service->handle($request->user(), $registry, $request->filters()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }
}
