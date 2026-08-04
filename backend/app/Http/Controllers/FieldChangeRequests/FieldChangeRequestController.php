<?php

declare(strict_types=1);

namespace App\Http\Controllers\FieldChangeRequests;

use App\Enums\FieldChangeRequestStatus;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\FieldChangeRequests\FieldChangeRequestForRecordRequest;
use App\Http\Requests\FieldChangeRequests\HandleFieldChangeRequestRequest;
use App\Http\Requests\FieldChangeRequests\StoreFieldChangeRequestRequest;
use App\Http\Resources\FieldChangeRequestResource;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Services\FieldChangeRequests\FieldChangeRequestApprover;
use App\Services\FieldChangeRequests\FieldChangeRequestCreator;
use App\Services\FieldChangeRequests\FieldChangeRequestRejecter;
use App\Services\FieldChangeRequests\FieldChangeRequestValueResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Thin controller for the generic field-change-request lifecycle (spec
 * 0078): every business rule (which field/value/status transition is legal)
 * lives in the Creator/Approver/Rejecter services; this class only gates
 * permissions, validates via FormRequest and shapes the Resource output.
 */
class FieldChangeRequestController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FieldChangeRequestCreator $creator,
        private readonly FieldChangeRequestApprover $approver,
        private readonly FieldChangeRequestRejecter $rejecter,
        private readonly FieldChangeRequestValueResolver $resolver,
    ) {}

    /**
     * POST /api/field-change-requests.
     */
    public function store(StoreFieldChangeRequestRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            abort_unless($user->can('field-change-requests.create'), HttpStatusEnum::FORBIDDEN->value);

            $fieldChangeRequest = $this->creator->handle($request->toData(), $user);

            return $this->created(new FieldChangeRequestResource($fieldChangeRequest->loadMissing(['requestedBy', 'handledBy'])), 'Change request created');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/field-change-requests/{fieldChangeRequest}.
     */
    public function show(Request $request, FieldChangeRequest $fieldChangeRequest): JsonResponse
    {
        try {
            $this->authorize('view', $fieldChangeRequest);

            return $this->ok(new FieldChangeRequestResource($fieldChangeRequest->loadMissing(['requestedBy', 'handledBy'])));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['fieldChangeRequest' => $fieldChangeRequest->id]);
        }
    }

    /**
     * GET /api/field-change-requests/for-record: the record's own list,
     * `pending` first then `created_at desc` (contract). A viewer without
     * `field-change-requests.viewAny` still sees the list when they are the
     * requester of AT LEAST ONE entry on it (AC-038), scoped to their OWN
     * requests only in that case.
     */
    public function forRecord(FieldChangeRequestForRecordRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $resource = $request->resource();

            $definition = $this->resolver->definitionFor($resource);
            $record = $this->resolver->record($definition, $request->subjectId());

            $query = FieldChangeRequest::query()
                ->with(['requestedBy', 'handledBy'])
                ->where('resource', $resource)
                ->where('subject_type', $record->getMorphClass())
                ->where('subject_id', $record->getKey());

            if (! $user->can('field-change-requests.viewAny')) {
                $query->where('requested_by_id', $user->id);
                abort_unless($query->exists(), HttpStatusEnum::FORBIDDEN->value);
            }

            return $this->ok(FieldChangeRequestResource::collection($this->pendingFirst($query)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/field-change-requests/{fieldChangeRequest}/approve.
     */
    public function approve(HandleFieldChangeRequestRequest $request, FieldChangeRequest $fieldChangeRequest): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            abort_unless($user->can('field-change-requests.manage'), HttpStatusEnum::FORBIDDEN->value);

            $handled = $this->approver->handle($fieldChangeRequest, $user, $request->note());

            return $this->ok(new FieldChangeRequestResource($handled->loadMissing(['requestedBy', 'handledBy'])), 'Change request approved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['fieldChangeRequest' => $fieldChangeRequest->id]);
        }
    }

    /**
     * POST /api/field-change-requests/{fieldChangeRequest}/reject.
     */
    public function reject(HandleFieldChangeRequestRequest $request, FieldChangeRequest $fieldChangeRequest): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            abort_unless($user->can('field-change-requests.manage'), HttpStatusEnum::FORBIDDEN->value);

            $handled = $this->rejecter->handle($fieldChangeRequest, $user, $request->note());

            return $this->ok(new FieldChangeRequestResource($handled->loadMissing(['requestedBy', 'handledBy'])), 'Change request rejected');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['fieldChangeRequest' => $fieldChangeRequest->id]);
        }
    }

    /**
     * `pending` entries (newest first) precede every handled one (also
     * newest first) — two plain ordered queries concatenated, never a
     * `whereRaw`/`orderByRaw` built from input (backend.md §8).
     *
     * @param  Builder<FieldChangeRequest>  $query
     * @return Collection<int, FieldChangeRequest>
     */
    private function pendingFirst(Builder $query): Collection
    {
        $pending = (clone $query)->where('status', FieldChangeRequestStatus::Pending)->orderByDesc('created_at')->get();
        $handled = (clone $query)->where('status', '!=', FieldChangeRequestStatus::Pending)->orderByDesc('created_at')->get();

        return $pending->concat($handled);
    }
}
