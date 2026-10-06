<?php

declare(strict_types=1);

namespace App\Http\Controllers\OwnerEmails;

use App\Enums\HttpStatusEnum;
use App\Http\Requests\WorkOrderEmails\StoreOutboundEmailRequest;
use App\Http\Requests\WorkOrderEmails\UpdateOutboundEmailRequest;
use App\Http\Resources\OutboundEmailListItemResource;
use App\Http\Resources\OutboundEmailResource;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Index/store/show/update/destroy/send of an owner's OutboundEmail
 * history+composer (spec 0175 data_contract "Email della commessa",
 * generalized by owner in spec 0195, D-10). Thin: owner-level authorization,
 * Service call, Resource output -- every other rule (draft visibility,
 * status conflicts, send validation) lives in OutboundEmailService.
 *
 * @see OutboundEmailService
 */
abstract class OwnerEmailController extends AbstractOwnerEmailController
{
    public function __construct(EmailOwnerRegistry $owners, private readonly OutboundEmailService $service)
    {
        parent::__construct($owners);
    }

    /**
     * A custom envelope (data + meta) since `meta` sits alongside `data`, not
     * nested under it (data_contract), mirroring NoteController::index().
     */
    protected function listEmails(Request $request, Model $owner): JsonResponse
    {
        try {
            $this->authorizeView($owner);

            $page = max(1, $request->integer('page', 1));
            $paginator = $this->service->listForOwner($owner, $this->actor($request), $page);

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => OutboundEmailListItemResource::collection($paginator->items())->resolve($request),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->failed($exception, 'index', $owner);
        }
    }

    protected function storeEmail(StoreOutboundEmailRequest $request, Model $owner): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $email = $this->service->createDraft($owner, $this->actor($request), $request->toData());

            return $this->created(new OutboundEmailResource($email));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'store', $owner);
        }
    }

    protected function showEmail(Request $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeView($owner);

            $resolved = $this->service->resolveVisibleOrFail($owner, $email, $this->actor($request));

            return $this->ok(new OutboundEmailResource($resolved));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'show', $owner, ['email' => $email]);
        }
    }

    protected function updateEmail(UpdateOutboundEmailRequest $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $resolved = $this->service->resolveVisibleOrFail($owner, $email, $this->actor($request));
            $updated = $this->service->updateDraft($resolved, $request->toData());

            return $this->ok(new OutboundEmailResource($updated));
        } catch (Throwable $exception) {
            return $this->failed($exception, 'update', $owner, ['email' => $email]);
        }
    }

    protected function destroyEmail(Request $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $resolved = $this->service->resolveVisibleOrFail($owner, $email, $this->actor($request));
            $this->service->deleteDraft($resolved);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->failed($exception, 'destroy', $owner, ['email' => $email]);
        }
    }

    protected function sendEmail(Request $request, Model $owner, int $email): JsonResponse
    {
        try {
            $this->authorizeSend($owner);

            $actor = $this->actor($request);
            $resolved = $this->service->resolveVisibleOrFail($owner, $email, $actor);
            $sent = $this->service->send($resolved, $actor);

            return $this->ok(new OutboundEmailResource($sent), 'Accepted', HttpStatusEnum::ACCEPTED);
        } catch (Throwable $exception) {
            return $this->failed($exception, 'send', $owner, ['email' => $email]);
        }
    }
}
