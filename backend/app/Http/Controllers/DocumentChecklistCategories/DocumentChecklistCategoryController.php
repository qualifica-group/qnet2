<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentChecklistCategories;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentChecklistCategories\SaveDocumentChecklistCategoryRequest;
use App\Http\Resources\DocumentChecklistCategoryResource;
use App\Models\DocumentChecklistCategory;
use App\Models\User;
use App\Services\DocumentChecklistCategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints of the `document-checklist-categories` resource (spec 0410). The
 * list is served by `POST /api/tables/document-checklist-categories/rows`; manual
 * sync and the sync log live in DocumentChecklistCategorySyncController.
 *
 * Thin controller: FormRequest, Policy, Service, Resource. Soft-deleted
 * categories are excluded by route model binding (404).
 *
 * @see DocumentChecklistCategoryService
 */
class DocumentChecklistCategoryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentChecklistCategoryService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/document-checklist-categories/{documentChecklistCategory}
     */
    public function show(Request $request, DocumentChecklistCategory $documentChecklistCategory): JsonResponse
    {
        try {
            $this->authorize('view', $documentChecklistCategory);

            return $this->respond($request->user(), $this->service->loadDetail($documentChecklistCategory));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklistCategory' => $documentChecklistCategory->id]);
        }
    }

    /**
     * POST /api/document-checklist-categories
     */
    public function store(SaveDocumentChecklistCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', DocumentChecklistCategory::class);

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
     * PUT /api/document-checklist-categories/{documentChecklistCategory}
     */
    public function update(SaveDocumentChecklistCategoryRequest $request, DocumentChecklistCategory $documentChecklistCategory): JsonResponse
    {
        try {
            $this->authorize('update', $documentChecklistCategory);

            return $this->respond($request->user(), $this->service->update($documentChecklistCategory, $request->toData()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklistCategory' => $documentChecklistCategory->id]);
        }
    }

    /**
     * DELETE /api/document-checklist-categories/{documentChecklistCategory} (soft delete + remote DELETE)
     */
    public function destroy(DocumentChecklistCategory $documentChecklistCategory): JsonResponse
    {
        try {
            $this->authorize('delete', $documentChecklistCategory);

            $this->service->delete($documentChecklistCategory);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentChecklistCategory' => $documentChecklistCategory->id]);
        }
    }

    private function respond(
        User $actor,
        DocumentChecklistCategory $category,
        string $message = 'OK',
        HttpStatusEnum $status = HttpStatusEnum::OK,
    ): JsonResponse {
        return $this->okWithPermissions(
            new DocumentChecklistCategoryResource($category),
            $this->permissionsBuilder->build($this->authorization->resolve('document-checklist-categories'), $actor, $category),
            $message,
            $status,
        );
    }
}
