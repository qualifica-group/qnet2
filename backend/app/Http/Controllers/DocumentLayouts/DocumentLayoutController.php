<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\StoreDocumentLayoutRequest;
use App\Http\Requests\DocumentLayouts\UpdateDocumentLayoutRequest;
use App\Http\Resources\DocumentLayoutResource;
use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayoutService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `document-layouts` resource (spec 0069), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (DocumentLayoutPolicy), Service call, response. No business logic, no
 * queries. No `index()`: the list is served generically by
 * `GET /api/tables/document-layouts/rows`.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see DocumentLayoutService
 */
class DocumentLayoutController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentLayoutService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/document-layouts/{documentLayout} — single document layout
     * (view row-action).
     */
    public function show(Request $request, DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('view', $documentLayout);

            $documentLayout = $this->service->loadDetail($documentLayout);

            return $this->okWithPermissions(
                new DocumentLayoutResource($documentLayout),
                $this->buildPermissions($request->user(), $documentLayout),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    /**
     * POST /api/document-layouts — create a new document layout.
     */
    public function store(StoreDocumentLayoutRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', DocumentLayout::class);

            $documentLayout = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new DocumentLayoutResource($documentLayout),
                $this->buildPermissions($request->user(), $documentLayout),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/document-layouts/{documentLayout} — update an existing
     * document layout.
     */
    public function update(UpdateDocumentLayoutRequest $request, DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('update', $documentLayout);

            $documentLayout = $this->service->update($documentLayout, $request->toData());

            return $this->okWithPermissions(
                new DocumentLayoutResource($documentLayout),
                $this->buildPermissions($request->user(), $documentLayout),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    /**
     * DELETE /api/document-layouts/{documentLayout} — delete a document
     * layout (D-7 default-guard only; no usage guard in this spec, D-10).
     */
    public function destroy(DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('delete', $documentLayout);

            $this->service->delete($documentLayout);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?DocumentLayout $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('document-layouts'), $actor, $model);
    }
}
