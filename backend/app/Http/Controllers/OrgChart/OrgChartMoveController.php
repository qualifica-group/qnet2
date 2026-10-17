<?php

namespace App\Http\Controllers\OrgChart;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OrgChart\MoveFunctionParentRequest;
use App\Http\Requests\OrgChart\MovePersonFunctionRequest;
use App\Http\Requests\OrgChart\MovePersonManagerRequest;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\OrgChart\OrgChartMoveService;
use App\Services\OrgChart\OrgChartReadService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Move endpoints of the org chart (spec 0273 D-4/D-7). Each is authorized by
 * its FormRequest (Policy of the data it edits); constraints and the activity entry live in
 * OrgChartMoveService.
 */
class OrgChartMoveController extends BaseApiController
{
    public function __construct(
        private readonly OrgChartMoveService $moves,
        private readonly OrgChartReadService $reader,
    ) {}

    /**
     * POST /api/org-chart/people/{user}/manager
     */
    public function manager(MovePersonManagerRequest $request, User $user): JsonResponse
    {
        try {

            $this->moves->moveManager(
                $request->user(),
                $user,
                $request->integer('from_manager_id') ?: null,
                $request->integer('to_manager_id') ?: null,
            );

            return $this->ok($this->reader->personWithManagers($user->refresh()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/org-chart/functions/{businessFunction}/parent
     */
    public function parent(MoveFunctionParentRequest $request, BusinessFunction $businessFunction): JsonResponse
    {
        try {

            $this->moves->moveFunctionParent($request->user(), $businessFunction, $request->integer('parent_id') ?: null);

            return $this->ok(['id' => $businessFunction->id, 'parent_id' => $businessFunction->parent_id]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['businessFunction' => $businessFunction->id]);
        }
    }

    /**
     * POST /api/org-chart/people/{user}/function
     */
    public function personFunction(MovePersonFunctionRequest $request, User $user): JsonResponse
    {
        try {

            $functionIds = $this->moves->movePersonFunction(
                $request->user(),
                $user,
                $request->integer('from_function_id'),
                $request->integer('to_function_id'),
            );

            return $this->ok(['id' => $user->id, 'business_function_ids' => $functionIds]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }
}
