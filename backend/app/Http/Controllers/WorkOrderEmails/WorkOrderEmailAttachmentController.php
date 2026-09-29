<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrderEmails;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderEmails\ImportWorkOrderEmailAttachmentRequest;
use App\Http\Requests\WorkOrderEmails\StoreWorkOrderEmailAttachmentRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\OutboundEmailAttachmentImportService;
use App\Services\OutboundEmails\OutboundEmailAttachmentService;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Upload/import/remove/download of an OutboundEmail's own allegati (spec
 * 0175, D-7/D-8). Every action first resolves the SAME
 * OutboundEmailService::resolveVisibleOrFail() the parent email endpoints
 * use, so a hidden draft or a foreign commessa's email 404s identically here
 * too — the draft-only / total-size rules live in the two attachment
 * Services, not in this controller.
 *
 * @see OutboundEmailAttachmentService
 * @see OutboundEmailAttachmentImportService
 */
class WorkOrderEmailAttachmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OutboundEmailService $emailService,
        private readonly OutboundEmailAttachmentService $attachmentService,
        private readonly OutboundEmailAttachmentImportService $importService,
    ) {}

    public function store(StoreWorkOrderEmailAttachmentRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->emailService->resolveVisibleOrFail($workOrder, $email, $actor);
            $updated = $this->attachmentService->upload($resolved, $request->file('file'));

            return $this->created(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }

    public function import(ImportWorkOrderEmailAttachmentRequest $request, WorkOrder $workOrder, int $email): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->emailService->resolveVisibleOrFail($workOrder, $email, $actor);
            $updated = $this->importService->import($workOrder, $resolved, $actor, $request->toData());

            return $this->created(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email]);
        }
    }

    public function destroy(Request $request, WorkOrder $workOrder, int $email, int $attachment): JsonResponse
    {
        try {
            $this->authorize('sendEmail', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->emailService->resolveVisibleOrFail($workOrder, $email, $actor);
            $updated = $this->attachmentService->remove($resolved, $attachment);

            return $this->ok(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email, 'attachment' => $attachment]);
        }
    }

    /**
     * GET .../attachments/{attachment}/download — gated by `viewEmails`
     * (not `sendEmail`, unlike every other action here): reading an
     * already-sent email's allegati needs no write ability, mirroring
     * AttachmentController::download()'s own shape.
     */
    public function download(Request $request, WorkOrder $workOrder, int $email, int $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('viewEmails', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $resolved = $this->emailService->resolveVisibleOrFail($workOrder, $email, $actor);
            $file = $this->attachmentService->resolveOwnedOrFail($resolved, $attachment);

            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->download($file->path, $file->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['work_order' => $workOrder->id, 'email' => $email, 'attachment' => $attachment]);
        }
    }
}
