<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklists;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentChecklists\SaveDocumentChecklistRequest;
use App\Http\Requests\DocumentChecklists\SetDocumentChecklistActivationRequest;
use App\Http\Resources\DocumentChecklistResource;
use App\Models\DocumentChecklist;
use App\Models\User;
use App\Services\DocumentChecklistService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints of the `document-checklists` resource (spec 0403). The list is
 * served by `POST /api/tables/document-checklists/rows`; manual sync and the
 * sync log live in DocumentChecklistSyncController.
 *
 * Thin controller: FormRequest, Policy, Service, Resource. Soft-deleted
 * checklists are excluded by route model binding (404).
 *
 * @see DocumentChecklistService
 */
class DocumentChecklistController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentChecklistService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/document-checklists/{documentChecklist}
     */
    public function show(Request $request, DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('view', $documentChecklist);

            return $this->respond($request->user(), $this->service->loadDetail($documentChecklist));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    /**
     * POST /api/document-checklists
     */
    public function store(SaveDocumentChecklistRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', DocumentChecklist::class);

            return $this->respond(
                $request->user(),
                $this->service->create($request->toData()),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/document-checklists/{documentChecklist}
     */
    public function update(SaveDocumentChecklistRequest $request, DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('update', $documentChecklist);

            return $this->respond($request->user(), $this->service->update($documentChecklist, $request->toData()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    /**
     * PATCH /api/document-checklists/{documentChecklist}/activation
     */
    public function activation(SetDocumentChecklistActivationRequest $request, DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('update', $documentChecklist);

            return $this->respond(
                $request->user(),
                $this->service->setActivation($documentChecklist, $request->isActive()),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    /**
     * DELETE /api/document-checklists/{documentChecklist} (soft delete + remote DELETE)
     */
    public function destroy(DocumentChecklist $documentChecklist): JsonResponse
    {
        try {
            $this->authorize('delete', $documentChecklist);

            $this->service->delete($documentChecklist);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklist' => $documentChecklist->id]);
        }
    }

    private function respond(
        User $actor,
        DocumentChecklist $checklist,
        string $message = 'OK',
        HttpStatusEnum $status = HttpStatusEnum::OK,
    ): JsonResponse {
        return $this->okWithPermissions(
            new DocumentChecklistResource($checklist),
            $this->permissionsBuilder->build($this->authorization->resolve('document-checklists'), $actor, $checklist),
            $message,
            $status,
        );
    }
}
