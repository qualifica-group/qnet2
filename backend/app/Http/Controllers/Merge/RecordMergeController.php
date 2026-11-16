<?php

namespace App\Http\Controllers\Merge;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Merge\MergePreviewRequest;
use App\Http\Requests\Merge\MergeRecordsRequest;
use App\Services\Merge\MergeDefinitionRegistry;
use App\Services\Merge\RecordMergeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Preview and execution of the duplicate merge for registries and referents
 * (spec 0394). The entity (`registries`/`referents`) is a route default, so the
 * same thin controller serves both; authorization goes through each module's
 * own policy (D-2).
 */
class RecordMergeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly MergeDefinitionRegistry $definitions,
        private readonly RecordMergeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * POST /api/{entity}/merge/preview — what the merge would unite.
     */
    public function preview(MergePreviewRequest $request, string $entity): JsonResponse
    {
        try {
            $definition = $this->definitions->for($entity);
            $records = $this->service->records($definition, $request->ids());

            foreach ($records as $record) {
                $this->authorize('view', $record);
            }

            return $this->ok($this->service->preview($definition, $records));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['entity' => $entity]);
        }
    }

    /**
     * POST /api/{entity}/merge — merge the duplicates into the survivor.
     */
    public function merge(MergeRecordsRequest $request, string $entity): JsonResponse
    {
        try {
            $definition = $this->definitions->for($entity);
            $data = $request->toData();

            foreach ($this->service->records($definition, [$data->survivorId, ...$data->mergedIds]) as $record) {
                $this->authorize($record->getKey() === $data->survivorId ? 'update' : 'delete', $record);
            }

            $survivor = $this->service->handle($request->user(), $definition, $data);
            $permissions = $this->permissionsBuilder->build($this->authorization->resolve($entity), $request->user(), $survivor);

            return $this->okWithPermissions($definition->resource($survivor, $permissions), $permissions, __('Records merged.'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['entity' => $entity]);
        }
    }
}
