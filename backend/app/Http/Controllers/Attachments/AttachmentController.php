<?php

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Attachments\IndexAttachmentRequest;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\RichText\RichText;
use App\Services\AttachmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Endpoints for the polymorphic file-attachment system: list, upload,
 * metadata, authenticated download/inline view and delete.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (AttachmentPolicy), Service call, response. No business logic, no queries.
 * The binary is never served statically — download/view stream through these
 * authorized endpoints only.
 *
 * @see AttachmentService
 */
class AttachmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly AttachmentService $service) {}

    /**
     * GET /api/attachments — list the files owned by one polymorphic owner,
     * optionally narrowed to a named collection, newest first.
     *
     * The `rich_text` collection (spec 0128, D-6) is never browsable here: an
     * explicit `collection=rich_text` is refused by the policy outright, and
     * the default "no collection" query excludes those rows so the generic
     * documents tab never surfaces images that only belong to a field's own
     * content.
     */
    public function index(IndexAttachmentRequest $request): JsonResponse
    {
        try {
            $collection = $request->validated('collection');
            $this->authorize('viewAny', [Attachment::class, $collection]);

            $attachments = Attachment::query()
                ->where('attachable_type', $request->validated('attachable_type'))
                ->where('attachable_id', $request->validated('attachable_id'))
                ->when(
                    $request->filled('collection'),
                    fn ($query) => $query->where('collection', $collection),
                    fn ($query) => $query->where(fn ($noRichText) => $noRichText
                        ->whereNull('collection')
                        ->orWhere('collection', '!=', RichText::ATTACHMENT_COLLECTION)),
                )
                ->latest()
                ->get();

            return $this->ok(AttachmentResource::collection($attachments));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/attachments — upload a file, optionally linking it to an owner.
     *
     * `collection=rich_text` is refused (D-6): those attachments are only
     * ever created by RichTextImageProcessor, inside the owning field's own
     * save transaction.
     */
    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', [Attachment::class, $request->validated('collection')]);

            $attachment = $this->service->store($request->user(), $request->toData());

            return $this->created(new AttachmentResource($attachment));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/attachments/{attachment} — file metadata.
     */
    public function show(Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            return $this->ok(new AttachmentResource($attachment));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * GET /api/attachments/{attachment}/download — stream the binary.
     *
     * Returns the raw file (not JSON) on success; falls back to the standard
     * JSON error envelope when the stored object is missing or unreadable.
     */
    public function download(Attachment $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->download($attachment->path, $attachment->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * GET /api/attachments/{attachment}/view — stream the binary inline, so a
     * browser/iframe renders it (e.g. PDF/image preview) instead of the
     * "Save as" prompt forced by download()'s Content-Disposition: attachment.
     *
     * Returns the raw file (not JSON) on success; falls back to the standard
     * JSON error envelope when the stored object is missing or unreadable.
     */
    public function view(Attachment $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->response(
                $attachment->path,
                $attachment->original_name,
                ['Content-Type' => $attachment->mime_type],
                'inline',
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * DELETE /api/attachments/{attachment} — delete metadata and binary.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('delete', $attachment);

            $this->service->delete($attachment);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }
}
