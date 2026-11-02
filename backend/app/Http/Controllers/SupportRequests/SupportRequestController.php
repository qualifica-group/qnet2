<?php

declare(strict_types=1);

namespace App\Http\Controllers\SupportRequests;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\SupportRequests\StoreSupportRequestRequest;
use App\Models\SupportTopic;
use App\Services\SupportRequests\SupportRequestService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * IT support requests of the end user (spec 0334). Open to ANY authenticated
 * user (D-7): the only gate is auth:sanctum, so there is no Policy here.
 */
class SupportRequestController extends BaseApiController
{
    public function __construct(private readonly SupportRequestService $service) {}

    /**
     * GET /api/support-requests/topics — the topics the user may pick, with
     * the user-facing keys only.
     */
    public function topics(): JsonResponse
    {
        try {
            $topics = SupportTopic::query()
                ->available()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'icon'])
                ->map(fn (SupportTopic $topic): array => $topic->only(['id', 'name', 'description', 'icon']))
                ->all();

            return $this->ok($topics);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/support-requests — creates the Task (and its attachments).
     */
    public function store(StoreSupportRequestRequest $request): JsonResponse
    {
        try {
            $actor = $request->user();
            $task = $this->service->handle($actor, $request->toData());

            return $this->ok(
                ['task_id' => $task->id, 'can_view_task' => $actor->can('view', $task)],
                __('Support request sent to the IT department'),
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
