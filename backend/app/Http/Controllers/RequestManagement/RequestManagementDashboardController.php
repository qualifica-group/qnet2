<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Enums\RequestManagementReportRowMode;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\RequestDashboardRequest;
use App\Http\Resources\RequestManagementDashboardResource;
use App\Models\User;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/request-management/report/dashboard (spec 0107, D-1): a
 * dedicated, SYNCHRONOUS endpoint (D-5 — no ExportRun, no queue, no
 * polling), deliberately not an extension of the generic stats framework
 * (spec 0026, D-1 — `StatsDefinition::widgets()` takes no parameters and
 * would need every one of its 14 domains touched for a need only this
 * module has). Reuses the `request-management.report` permission verbatim
 * (D-6) — same aggregates as the CSV, no separate grant.
 */
class RequestManagementDashboardController extends BaseApiController
{
    private const string PERMISSION = 'request-management.report';

    public function __construct(private readonly RequestManagementDashboardBuilder $builder) {}

    public function __invoke(RequestDashboardRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can(self::PERMISSION), 403);

            $dateFrom = (string) $request->validated('date_from');
            $dateTo = (string) $request->validated('date_to');
            /** @var array<int, string> $categoryKeys */
            $categoryKeys = (array) $request->validated('category_keys');
            $rowMode = (string) $request->validated('row_mode');
            $operatorKeys = $request->operatorKeys();

            $result = $this->builder->build(
                $actor,
                $dateFrom,
                $dateTo,
                $categoryKeys,
                RequestManagementReportRowMode::from($rowMode),
                ReportOperatorFilter::fromKeysOrAll($operatorKeys),
            );

            return $this->ok([
                'applied' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'category_keys' => $categoryKeys,
                    'row_mode' => $rowMode,
                    // null echoes back "every operator" (spec 0108 D-2), so the
                    // client can tell an unfiltered response from a filtered one.
                    'operator_keys' => $operatorKeys,
                ],
                ...(new RequestManagementDashboardResource($result))->resolve(),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
