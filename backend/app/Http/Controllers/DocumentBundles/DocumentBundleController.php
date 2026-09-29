<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentBundles;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentBundles\StoreDocumentBundleRequest;
use App\Http\Requests\DocumentBundles\UpdateDocumentBundleRequest;
use App\Http\Resources\DocumentBundleResource;
use App\Models\DocumentBundle;
use App\Models\User;
use App\Services\DocumentBundleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `document-bundles` resource (spec 0175, D-7c/D-14),
 * backing the backend-driven table row-actions (view/edit/delete) plus
 * create. List is served generically by `POST /api/tables/document-bundles/rows`.
 * The bundle's files pass through the existing `/api/attachments` endpoints
 * (alias `document_bundle`), never here.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (DocumentBundlePolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see DocumentBundleService
 */
class DocumentBundleController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentBundleService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/document-bundles/{documentBundle} — single document bundle (view row-action).
     */
    public function show(Request $request, DocumentBundle $documentBundle): JsonResponse
    {
        try {
            $this->authorize('view', $documentBundle);

            $documentBundle = $this->service->withFilesCount($documentBundle);

            return $this->okWithPermissions(
                new DocumentBundleResource($documentBundle),
                $this->buildPermissions($request->user(), $documentBundle),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentBundle' => $documentBundle->id]);
        }
    }

    /**
     * POST /api/document-bundles — create a new document bundle.
     */
    public function store(StoreDocumentBundleRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', DocumentBundle::class);

            $documentBundle = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new DocumentBundleResource($documentBundle),
                $this->buildPermissions($request->user(), $documentBundle),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/document-bundles/{documentBundle} — update an existing document bundle.
     */
    public function update(UpdateDocumentBundleRequest $request, DocumentBundle $documentBundle): JsonResponse
    {
        try {
            $this->authorize('update', $documentBundle);

            $documentBundle = $this->service->update($documentBundle, $request->toData());

            return $this->okWithPermissions(
                new DocumentBundleResource($documentBundle),
                $this->buildPermissions($request->user(), $documentBundle),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentBundle' => $documentBundle->id]);
        }
    }

    /**
     * DELETE /api/document-bundles/{documentBundle} — delete a document
     * bundle (HasAttachments cascades: its files are removed with it).
     */
    public function destroy(DocumentBundle $documentBundle): JsonResponse
    {
        try {
            $this->authorize('delete', $documentBundle);

            $this->service->delete($documentBundle);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentBundle' => $documentBundle->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?DocumentBundle $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('document-bundles'), $actor, $model);
    }
}
