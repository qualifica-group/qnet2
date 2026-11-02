<?php

declare(strict_types=1);

namespace App\Http\Controllers\SupportTopics;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\SupportTopics\StoreSupportTopicRequest;
use App\Http\Requests\SupportTopics\UpdateSupportTopicRequest;
use App\Http\Resources\SupportTopicResource;
use App\Models\SupportTopic;
use App\Models\User;
use App\Services\SupportTopicService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints of the `support-topics` configuration (spec 0334). Thin:
 * FormRequest, SupportTopicPolicy, Service, Resource.
 */
class SupportTopicController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SupportTopicService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, SupportTopic $supportTopic): JsonResponse
    {
        try {
            $this->authorize('view', $supportTopic);

            return $this->detail($request->user(), $this->service->loadDetail($supportTopic));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['supportTopic' => $supportTopic->id]);
        }
    }

    public function store(StoreSupportTopicRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', SupportTopic::class);

            return $this->detail(
                $request->user(),
                $this->service->create($request->toData()),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateSupportTopicRequest $request, SupportTopic $supportTopic): JsonResponse
    {
        try {
            $this->authorize('update', $supportTopic);

            return $this->detail($request->user(), $this->service->update($supportTopic, $request->toData()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['supportTopic' => $supportTopic->id]);
        }
    }

    public function destroy(SupportTopic $supportTopic): JsonResponse
    {
        try {
            $this->authorize('delete', $supportTopic);

            $this->service->delete($supportTopic);

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['supportTopic' => $supportTopic->id]);
        }
    }

    private function detail(User $actor, SupportTopic $topic, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            new SupportTopicResource($topic),
            $this->permissionsBuilder->build($this->authorization->resolve('support-topics'), $actor, $topic),
            $message,
            $status,
        );
    }
}
