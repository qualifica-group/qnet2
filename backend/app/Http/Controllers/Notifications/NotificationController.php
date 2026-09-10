<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Notifications\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * In-app user notification endpoints (Laravel native `database` channel).
 *
 * Thin controller: validation (FormRequest), Service call, response. No business
 * logic, no queries.
 *
 * No Policy / Spatie permission is involved: these endpoints are self-scoped by
 * construction — they always operate on the authenticated user's own
 * notifications via the relationship, so a foreign / unknown id simply resolves
 * to 404. Authorization is ownership, not a permission check (see ADR-0005).
 *
 * @see NotificationService
 */
class NotificationController extends BaseApiController
{
    public function __construct(private readonly NotificationService $service) {}

    /**
     * GET /api/notifications — paginated list of the actor's notifications.
     */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        try {
            $result = $this->service->list($request->user(), $request->toData());

            return $this->paginatedResponse(
                NotificationResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/notifications/unread-count — actor's unread count plus the most
     * recent unread notification, in one polled call (badge + tab title).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->unreadSummary($request->user());

            return $this->ok([
                'count' => $summary->count,
                'latest' => $summary->latest ? new NotificationResource($summary->latest) : null,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PATCH /api/notifications/{notification}/read — mark one read (idempotent).
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        try {
            $marked = $this->service->markAsRead($request->user(), $notification);

            return $this->ok(new NotificationResource($marked));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['notification' => $notification]);
        }
    }

    /**
     * POST /api/notifications/read-all — mark all unread read, return the count.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            return $this->ok(['marked' => $this->service->markAllAsRead($request->user())]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
