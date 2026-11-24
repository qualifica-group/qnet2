<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\ArchivioSmartWebhookEventStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Jobs\ProcessArchivioSmartWebhookJob;
use App\Models\ArchivioSmartWebhookEvent;
use App\Services\DocumentRequests\DocumentRequestRemoteWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * POST /api/webhooks/archivio-smart (spec 0428, D-6). The signature is checked
 * by VerifyArchivioSmartSignature. The event is recorded (unique event id, so a
 * duplicate delivery is acknowledged and not processed again) and handed to the queue.
 */
class ArchivioSmartWebhookController extends BaseApiController
{
    private const int EVENT_ID_MAX_LENGTH = 100;

    private const int EVENT_NAME_MAX_LENGTH = 64;

    private const string UNKNOWN_EVENT = 'unknown';

    public function __construct(private readonly DocumentRequestRemoteWriter $dates) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Step 1: identify the event
            $payload = json_decode($request->getContent(), true);
            $payload = is_array($payload) ? $payload : [];
            $eventId = $this->headerOrBody($request, 'X-Archiviosmart-Event-Id', $payload, 'event_id') ?? hash('sha256', $request->getContent());
            $name = $this->headerOrBody($request, 'X-Archiviosmart-Event', $payload, 'event') ?? self::UNKNOWN_EVENT;

            // Step 2: record it once and queue it, unless it is a duplicate. One
            // transaction: if queueing fails the record is rolled back, so the retried
            // delivery is not mistaken for a duplicate and lost.
            $event = DB::transaction(function () use ($request, $payload, $eventId, $name): ArchivioSmartWebhookEvent {
                $event = ArchivioSmartWebhookEvent::query()->createOrFirst(
                    ['event_id' => Str::limit($eventId, self::EVENT_ID_MAX_LENGTH, '')],
                    [
                        'event' => Str::limit($name, self::EVENT_NAME_MAX_LENGTH, ''),
                        'occurred_at' => $this->occurredAt($request, $payload),
                        'status' => ArchivioSmartWebhookEventStatus::Received,
                    ],
                );

                if ($event->wasRecentlyCreated) {
                    ProcessArchivioSmartWebhookJob::dispatch($event->id, $payload);
                }

                return $event;
            });

            Log::info('ArchivioSmart webhook accepted.', ['event' => $event->event, 'duplicate' => ! $event->wasRecentlyCreated]);

            return response()->json(['success' => true], 202);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function headerOrBody(Request $request, string $header, array $payload, string $key): ?string
    {
        $value = $request->header($header) ?? ($payload[$key] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function occurredAt(Request $request, array $payload): mixed
    {
        $stamp = $request->header('X-Archiviosmart-Timestamp');

        return $this->dates->date($payload['occurred_at'] ?? null)
            ?? (is_string($stamp) && ctype_digit($stamp) ? now()->setTimestamp((int) $stamp) : $this->dates->date($stamp));
    }
}
