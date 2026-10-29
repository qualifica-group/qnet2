<?php

namespace App\Http\Controllers\StaffTrainings;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\StaffTrainings\StaffTrainingAttachmentRequest;
use App\Http\Resources\StaffTrainingResource;
use App\Models\Attachment;
use App\Models\StaffTraining;
use App\Services\AttachmentService;
use App\Services\StaffTrainings\StaffTrainingService;
use App\Services\StaffTrainings\StaffTrainingVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The single certificate of a staff training (spec 0284 D-3). Visibility
 * follows the training (404 for a stranger); writing needs `manage`. The
 * generic /api/attachments endpoints never serve it.
 */
class StaffTrainingAttachmentController extends BaseApiController
{
    public function __construct(
        private readonly StaffTrainingService $service,
        private readonly StaffTrainingVisibility $visibility,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * POST /api/staff-trainings/{staffTraining}/attachment (replaces the current file)
     */
    public function store(StaffTrainingAttachmentRequest $request, StaffTraining $staffTraining): JsonResponse
    {
        try {
            // Step 1: store the new file, then drop the old one
            $previous = $staffTraining->attachments()->get();
            $staffTraining->attach($request->file('file'));
            $previous->each(fn (Attachment $old) => $this->attachments->delete($old));

            return $this->ok(new StaffTrainingResource($this->service->detail($staffTraining)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }

    /**
     * GET /api/staff-trainings/{staffTraining}/attachment
     */
    public function show(Request $request, StaffTraining $staffTraining): StreamedResponse|JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $staffTraining);

            /** @var Attachment|null $attachment */
            $attachment = $staffTraining->attachments()->first();
            $disk = $attachment === null ? null : Storage::disk($attachment->disk);
            abort_if($attachment === null || ! $disk->exists($attachment->path), 404, 'Resource not found.');

            return $disk->download($attachment->path, $attachment->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }

    /**
     * DELETE /api/staff-trainings/{staffTraining}/attachment
     */
    public function destroy(Request $request, StaffTraining $staffTraining): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $staffTraining);
            abort_unless($this->visibility->canManage($request->user()), 403, __('You cannot manage staff trainings.'));

            $staffTraining->attachments()->get()->each(fn (Attachment $old) => $this->attachments->delete($old));

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }
}
