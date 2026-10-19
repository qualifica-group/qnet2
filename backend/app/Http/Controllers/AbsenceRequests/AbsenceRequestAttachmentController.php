<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceRequests\AbsenceRequestAttachmentRequest;
use App\Http\Resources\AbsenceRequestResource;
use App\Models\AbsenceRequest;
use App\Models\Attachment;
use App\Services\Absences\Requests\AbsenceRequestCapabilities;
use App\Services\Absences\Requests\AbsenceRequestService;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The single optional certificate of an absence request (spec 0278, D-12).
 * Visibility follows the request (404 for a stranger); writing needs
 * `can.attach`. The generic /api/attachments endpoints never serve it.
 */
class AbsenceRequestAttachmentController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceRequestService $service,
        private readonly AbsenceRequestVisibility $visibility,
        private readonly AbsenceRequestCapabilities $capabilities,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * POST /api/absence-requests/{absenceRequest}/attachment (replaces the current file)
     */
    public function store(AbsenceRequestAttachmentRequest $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $this->assertCanAttach($request, $absenceRequest);

            // Step 1: store the new file, then drop the old one
            $previous = $absenceRequest->attachments()->get();
            $absenceRequest->attach($request->file('file'));
            $previous->each(fn (Attachment $old) => $this->attachments->delete($old));

            return $this->ok(new AbsenceRequestResource($this->service->detail($absenceRequest->unsetRelation('attachments'))));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }

    /**
     * GET /api/absence-requests/{absenceRequest}/attachment
     */
    public function show(Request $request, AbsenceRequest $absenceRequest): StreamedResponse|JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);

            /** @var Attachment|null $attachment */
            $attachment = $absenceRequest->attachments()->first();
            $disk = $attachment === null ? null : Storage::disk($attachment->disk);
            abort_if($attachment === null || ! $disk->exists($attachment->path), 404, 'Resource not found.');

            return $disk->download($attachment->path, $attachment->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }

    /**
     * DELETE /api/absence-requests/{absenceRequest}/attachment
     */
    public function destroy(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);
            $this->assertCanAttach($request, $absenceRequest);

            $absenceRequest->attachments()->get()->each(fn (Attachment $old) => $this->attachments->delete($old));

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }

    private function assertCanAttach(Request $request, AbsenceRequest $absenceRequest): void
    {
        abort_unless($this->capabilities->canAttach($request->user(), $absenceRequest), 403, 'This action is unauthorized.');
    }
}
