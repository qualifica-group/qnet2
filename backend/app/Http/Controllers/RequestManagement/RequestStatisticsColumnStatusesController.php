<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\UpdateRequestStatisticsColumnStatusesRequest;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\ReportColumnStatusCatalog;
use App\Services\RequestManagement\Report\ReportColumnStatusMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET/PUT /api/request-management/report/column-statuses (spec 0391): which
 * working statuses each status-based statistics column counts. Reading needs
 * `request-statistics.view`, saving `request-statistics.update`.
 */
class RequestStatisticsColumnStatusesController extends BaseApiController
{
    public function __construct(
        private readonly ReportColumnStatusCatalog $catalog,
        private readonly ReportColumnStatusMap $statuses,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            Gate::forUser($request->user())->authorize(RequestModule::STATISTICS_PERMISSION);

            return $this->ok($this->catalog->build());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateRequestStatisticsColumnStatusesRequest $request): JsonResponse
    {
        try {
            Gate::forUser($request->user())->authorize(RequestModule::STATISTICS_CONFIGURE_PERMISSION);

            /** @var array<string, array<int, int>> $columns */
            $columns = $request->safe()->only(['columns'])['columns'];
            $this->statuses->replace($columns);

            return $this->ok($this->catalog->build());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
