<?php

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * GET /api/ai-assistant/messages/{message}/attachments/{attachment} — private
 * download, allowed only to the owner of the message's conversation.
 */
class AiAttachmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function download(AiMessage $message, Attachment $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $message->load('conversation')->conversation);

            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->download($attachment->path, $attachment->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }
}
