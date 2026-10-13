<?php

namespace App\Http\Controllers\EnasarcoParameters;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EnasarcoParameters\EnasarcoParameterRequest;
use App\Http\Resources\EnasarcoParameterResource;
use App\Models\EnasarcoParameter;
use App\Models\User;
use App\Services\EnasarcoParameterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `enasarco-parameters` resource (spec 0213). Thin
 * controller: FormRequest validation, server-side authorization
 * (EnasarcoParameterPolicy), Service call, response.
 *
 * @see EnasarcoParameterService
 */
class EnasarcoParameterController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EnasarcoParameterService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/enasarco-parameters/{enasarcoParameter}
     */
    public function show(Request $request, EnasarcoParameter $enasarcoParameter): JsonResponse
    {
        try {
            $this->authorize('view', $enasarcoParameter);

            return $this->respond($request->user(), $enasarcoParameter);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['enasarcoParameter' => $enasarcoParameter->id]);
        }
    }

    /**
     * POST /api/enasarco-parameters
     */
    public function store(EnasarcoParameterRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', EnasarcoParameter::class);

            $parameter = $this->service->create($request->toData());

            return $this->respond($request->user(), $parameter, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/enasarco-parameters/{enasarcoParameter}
     */
    public function update(EnasarcoParameterRequest $request, EnasarcoParameter $enasarcoParameter): JsonResponse
    {
        try {
            $this->authorize('update', $enasarcoParameter);

            $parameter = $this->service->update($enasarcoParameter, $request->toData());

            return $this->respond($request->user(), $parameter);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['enasarcoParameter' => $enasarcoParameter->id]);
        }
    }

    /**
     * DELETE /api/enasarco-parameters/{enasarcoParameter}
     */
    public function destroy(EnasarcoParameter $enasarcoParameter): JsonResponse
    {
        try {
            $this->authorize('delete', $enasarcoParameter);

            $this->service->delete($enasarcoParameter);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['enasarcoParameter' => $enasarcoParameter->id]);
        }
    }

    private function respond(User $actor, EnasarcoParameter $parameter, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        $permissions = $this->permissionsBuilder->build($this->authorization->resolve('enasarco-parameters'), $actor, $parameter);

        return $this->okWithPermissions(
            new EnasarcoParameterResource($parameter, $permissions['fields']),
            $permissions,
            $message,
            $status,
        );
    }
}
