<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\Workspaces\NavigationCatalogue;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Super-admin CRUD of workspaces and their menu catalogue (spec 0317). The
 * `super-admin` middleware on the routes is the authorization.
 */
class WorkspaceController extends BaseApiController
{
    public function __construct(
        private readonly WorkspaceService $service,
        private readonly NavigationCatalogue $catalogue,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $workspaces = Workspace::query()->with('items')->orderBy('sort_order')->orderBy('name')->get();

            return $this->ok(WorkspaceResource::collection($workspaces));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function catalogue(): JsonResponse
    {
        return $this->ok($this->catalogue->entries());
    }

    public function show(Workspace $workspace): JsonResponse
    {
        try {
            return $this->ok(new WorkspaceResource($workspace->load('items')));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workspace' => $workspace->id]);
        }
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        try {
            $workspace = $this->service->create($request->safe()->all());

            return $this->ok(new WorkspaceResource($workspace), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        try {
            $workspace = $this->service->update($workspace, $request->safe()->all());

            return $this->ok(new WorkspaceResource($workspace));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workspace' => $workspace->id]);
        }
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        try {
            $this->service->delete($workspace);

            return $this->ok(null, 'Deleted');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workspace' => $workspace->id]);
        }
    }
}
