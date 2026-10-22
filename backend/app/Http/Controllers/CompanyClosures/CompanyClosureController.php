<?php

namespace App\Http\Controllers\CompanyClosures;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CompanyClosures\StoreCompanyClosureRequest;
use App\Http\Requests\CompanyClosures\UpdateCompanyClosureRequest;
use App\Http\Resources\CompanyClosureResource;
use App\Models\CompanyClosure;
use App\Models\User;
use App\Services\CompanyClosures\CompanyClosureService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints of the `company-closures` resource (spec 0304 D-13). Thin
 * controller: validation (FormRequest), authorization (CompanyClosurePolicy),
 * service call, response with the `permissions` block (spec 0004). The list is
 * the generic `company-closures` table domain.
 *
 * @see CompanyClosureService
 */
class CompanyClosureController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CompanyClosureService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, CompanyClosure $companyClosure): JsonResponse
    {
        try {
            $this->authorize('view', $companyClosure);

            return $this->respond($request->user(), $this->service->detail($companyClosure));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companyClosure' => $companyClosure->id]);
        }
    }

    public function store(StoreCompanyClosureRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CompanyClosure::class);

            $result = $this->service->create($request->toAttributes(), $request->user());

            return $this->respond($request->user(), $result['closure'], $result['generation'], 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCompanyClosureRequest $request, CompanyClosure $companyClosure): JsonResponse
    {
        try {
            $this->authorize('update', $companyClosure);

            $result = $this->service->update($companyClosure, $request->toAttributes(), $request->user());

            return $this->respond($request->user(), $result['closure'], $result['generation']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companyClosure' => $companyClosure->id]);
        }
    }

    public function destroy(CompanyClosure $companyClosure): JsonResponse
    {
        try {
            $this->authorize('delete', $companyClosure);

            $this->service->delete($companyClosure);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companyClosure' => $companyClosure->id]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $generation
     */
    private function respond(User $actor, CompanyClosure $closure, ?array $generation = null, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            (new CompanyClosureResource($closure, $generation))->resolve(),
            $this->permissionsBuilder->build($this->authorization->resolve('company-closures'), $actor, $closure),
            $message,
            $status,
        );
    }
}
