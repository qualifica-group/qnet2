<?php

declare(strict_types=1);

namespace App\Http\Controllers\DocumentLayouts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\DocumentLayouts\UploadDocumentLayoutImageRequest;
use App\Http\Resources\DocumentLayoutImageResource;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Services\DocumentLayouts\DocumentLayoutImageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Endpoints for a document layout's own uploaded images (logos / carta
 * intestata, spec 0069, MT-4): list/upload/delete against the `layout_image`
 * attachment collection. Every binary stays on the private `local` disk and
 * is only ever exposed as an authenticated `data_uri` (never a public URL),
 * mirroring CompanySite::logoDataUri().
 *
 * Thin controller: server-side authorization (DocumentLayoutPolicy — `view`
 * for index, `update` for store/destroy), validation (FormRequest for
 * store), Service call, response. Ownership of the nested {attachment} and
 * the `image_in_use` config-reference guard both live in the Service, not
 * here (see routes/api/document-layouts.php's docblock on why these routes
 * are not `scopeBindings()`-wrapped).
 *
 * @see DocumentLayoutImageService
 */
class DocumentLayoutImageController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly DocumentLayoutImageService $service) {}

    /**
     * GET /api/document-layouts/{documentLayout}/images — metadata + data_uri
     * for every image uploaded to this layout.
     */
    public function index(DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('view', $documentLayout);

            $images = $this->service->list($documentLayout);

            return $this->ok(DocumentLayoutImageResource::collection($images));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    /**
     * POST /api/document-layouts/{documentLayout}/images — upload a new
     * image, rejected past MAX_IMAGES_PER_LAYOUT.
     */
    public function store(UploadDocumentLayoutImageRequest $request, DocumentLayout $documentLayout): JsonResponse
    {
        try {
            $this->authorize('update', $documentLayout);

            $attachment = $this->service->store($documentLayout, $request->imageFile());

            return $this->created(new DocumentLayoutImageResource($attachment));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id]);
        }
    }

    /**
     * DELETE /api/document-layouts/{documentLayout}/images/{attachment} —
     * remove an image, 404 if it does not belong to this layout, 422
     * (`image_in_use`) if the current config still references it.
     */
    public function destroy(DocumentLayout $documentLayout, Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('update', $documentLayout);

            $this->service->delete($documentLayout, $attachment);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['documentLayout' => $documentLayout->id, 'attachment' => $attachment->id]);
        }
    }
}
