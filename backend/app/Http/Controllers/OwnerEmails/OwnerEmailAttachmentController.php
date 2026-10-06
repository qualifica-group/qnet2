<?php

declare(strict_types=1);

namespace App\Http\Controllers\OwnerEmails;

use App\Http\Requests\WorkOrderEmails\ImportWorkOrderEmailAttachmentRequest;
use App\Http\Requests\WorkOrderEmails\StoreWorkOrderEmailAttachmentRequest;
use App\Http\Resources\OutboundEmailResource;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailAttachmentImportService;
use App\Services\OutboundEmails\OutboundEmailAttachmentService;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Upload/import/remove/download of an OutboundEmail's own allegati (spec
 * 0175, D-7/D-8; generalized by owner in spec 0195, D-10). Every action first
 * resolves the SAME OutboundEmailService::resolveVisibleOrFail() the parent
 * email endpoints use, so a hidden draft or a foreign owner's email 404s
 * identically here too -- the draft-only / total-size rules live in the two
 * attachment Services, not in this controller.
 *
 * @see OutboundEmailAttachmentService
 * @see OutboundEmailAttachmentImportService
 */
abstract class OwnerEmailAttachmentController extends AbstractOwnerEmailController
{
    public function __construct(
        EmailOwnerRegistry $owners,
        private readonly OutboundEmailService $emailService,
        private readonly OutboundEmailAttachmentService $attachmentService,
        private readonly OutboundEmailAttachmentImportService $importService,
    ) {
        parent::__construct($owners);
    }

    protected function storeAttachment(StoreWorkOrderEmailAttachmentRequest $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $resolved = $this->emailService->resolveVisibleOrFail($owner, $email, $this->actor($request));
            $updated = $this->attachmentService->upload($resolved, $request->file('file'));

            return $this->created(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'store', $owner, ['email' => $email]);
        }
    }

    protected function importAttachments(ImportWorkOrderEmailAttachmentRequest $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $actor = $this->actor($request);
            $resolved = $this->emailService->resolveVisibleOrFail($owner, $email, $actor);
            $updated = $this->importService->import($owner, $resolved, $actor, $request->toData());

            return $this->created(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'import', $owner, ['email' => $email]);
        }
    }

    protected function destroyAttachment(Request $request, Model $owner, int $email, int $attachment): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $resolved = $this->emailService->resolveVisibleOrFail($owner, $email, $this->actor($request));
            $updated = $this->attachmentService->remove($resolved, $attachment);

            return $this->ok(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'destroy', $owner, ['email' => $email, 'attachment' => $attachment]);
        }
    }

    /**
     * Gated by the view ability (not the send one, unlike every other action
     * here): reading an already-sent email's allegati needs no write
     * ability, mirroring AttachmentController::download()'s own shape.
     */
    protected function downloadAttachment(Request $request, Model $owner, int $email, int $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorizeView($owner);

            $resolved = $this->emailService->resolveVisibleOrFail($owner, $email, $this->actor($request));
            $file = $this->attachmentService->resolveOwnedOrFail($resolved, $attachment);

            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->download($file->path, $file->original_name);
        } catch (Throwable $exception) {
            return $this->failed($exception, 'download', $owner, ['email' => $email, 'attachment' => $attachment]);
        }
    }
}
