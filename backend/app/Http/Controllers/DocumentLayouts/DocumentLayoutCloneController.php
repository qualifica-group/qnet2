<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\CloneDocumentLayoutRequest;
use App\Http\Resources\DocumentLayoutResource;
use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutCloner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/document-layouts/{documentLayout}/clone — a new layout copied
 * from this one (spec 0339), gated by `view` on the source AND `create`.
 * Answers like POST /api/document-layouts: the NEW layout with its
 * `permissions` block.
 */
class DocumentLayoutCloneController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentLayoutCloner $cloner,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(CloneDocumentLayoutRequest $request, DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('view', $documentLayout);
            $this->authorize('create', DocumentLayout::class);

            /** @var User $actor */
            $actor = $request->user();

            $clone = $this->cloner->handle($documentLayout, $request->toData(), $actor);

            return $this->okWithPermissions(
                new DocumentLayoutResource($clone),
                $this->permissionsBuilder->build($this->authorization->resolve('document-layouts'), $actor, $clone),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }
}
