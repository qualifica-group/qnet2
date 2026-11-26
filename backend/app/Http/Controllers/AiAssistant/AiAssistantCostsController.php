<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AiAssistant\IndexAiAssistantCostsRequest;
use App\Services\AiAssistant\Costs\AiCostReport;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Costs of the AI assistant (spec 0433). Authorization is the `super-admin` route middleware.
 */
class AiAssistantCostsController extends BaseApiController
{
    public function __construct(private readonly AiCostReport $report) {}

    /**
     * GET /api/ai-assistant/costs
     */
    public function index(IndexAiAssistantCostsRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->report->build($request->from(), $request->to()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
