<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\DataObjects\Absences\AbsenceRequestData;
use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create, edit, delete and submit absence requests (spec 0278). The
 * approve/reject/cancel transitions live in AbsenceRequestTransitions.
 */
final class AbsenceRequestService
{
    /** Relations the AbsenceRequestResource reads (lazy loading is forbidden). */
    public const array RELATIONS = [
        'user:id,name',
        'absenceType:id,name,deducts_balance,requires_approval',
        'approvers:id,name',
        'decidedBy:id,name',
        'attachments',
    ];

    public function __construct(
        private readonly AbsenceDurationCalculator $duration,
        private readonly AbsenceOverlapGuard $overlap,
        private readonly AbsenceApproverResolver $approvers,
        private readonly AbsenceRequestVisibility $visibility,
        private readonly AbsenceRequestNotifier $notifier,
    ) {}

    public function detail(AbsenceRequest $request): AbsenceRequest
    {
        return $request->load(self::RELATIONS);
    }

    public function create(AbsenceRequestData $data, User $person, User $actor, bool $submit): AbsenceRequest
    {
        return DB::transaction(function () use ($data, $person, $actor, $submit): AbsenceRequest {
            // Step 1: the draft must already be coherent (working days, hours, overlap)
            $this->assertSchedule($person->id, $data);

            // Step 2: persist the draft
            $request = AbsenceRequest::query()->create([
                ...$data->toAttributes(),
                'user_id' => $person->id,
                'created_by' => $actor->id,
                'status' => AbsenceRequestStatus::Draft,
            ]);

            // Step 3: file it straight away when asked
            return $submit ? $this->submit($request, $actor) : $this->detail($request);
        });
    }

    public function update(AbsenceRequest $request, AbsenceRequestData $data, User $actor): AbsenceRequest
    {
        $this->assertDraftEditable($request, $actor);
        $this->assertSchedule($request->user_id, $data, $request->id);

        $request->update($data->toAttributes());

        return $this->detail($request);
    }

    public function delete(AbsenceRequest $request, User $actor): void
    {
        $this->assertDraftEditable($request, $actor);

        $request->delete();
    }

    public function submit(AbsenceRequest $request, User $actor): AbsenceRequest
    {
        return DB::transaction(function () use ($request, $actor): AbsenceRequest {
            // Step 1: only the person (or the HR office) files a draft
            $request = AbsenceRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertDraftEditable($request, $actor);

            // Step 2: freeze the duration and re-check the overlap
            $minutes = $this->frozenMinutes($request);

            // Step 3: snapshot the approvers (none = 422)
            $approvers = $this->approvers->resolve(User::query()->findOrFail($request->user_id));

            if ($approvers->isEmpty()) {
                throw ValidationException::withMessages(['user_id' => [__('absence_requests.no_approvers')]]);
            }

            // Step 4: submitted, or approved at once when the type needs no approval (D-8)
            $autoApproved = ! AbsenceType::query()->findOrFail($request->absence_type_id)->requires_approval;
            $request->update([
                'status' => $autoApproved ? AbsenceRequestStatus::Approved : AbsenceRequestStatus::Submitted,
                'minutes' => $minutes,
                'submitted_at' => now(),
                'decided_at' => $autoApproved ? now() : null,
            ]);
            $request->approvers()->sync($approvers->modelKeys());

            // Step 5: tell who needs to know, once the transaction commits
            $detail = $this->detail($request);
            $this->notifier->submitted($detail, $actor);

            return $detail;
        });
    }

    private function frozenMinutes(AbsenceRequest $request): int
    {
        $startsOn = $request->starts_on->format('Y-m-d');
        $endsOn = $request->ends_on->format('Y-m-d');

        $minutes = $this->duration->minutes($request->user_id, $request->is_full_day, $startsOn, $endsOn, $request->requested_minutes);
        $this->overlap->assertFree($request->user_id, $startsOn, $endsOn, $request->id);

        return $minutes;
    }

    private function assertSchedule(int $userId, AbsenceRequestData $data, ?int $ignoreRequestId = null): void
    {
        $this->duration->minutes($userId, $data->isFullDay, $data->startsOn, $data->endsOn, $data->requestedMinutes);
        $this->overlap->assertFree($userId, $data->startsOn, $data->endsOn, $ignoreRequestId);
    }

    /** 403 for a stranger to the request, 409 once it left the draft state. */
    private function assertDraftEditable(AbsenceRequest $request, User $actor): void
    {
        abort_unless($this->visibility->isOwnerOrManager($actor, $request), 403, 'This action is unauthorized.');
        abort_unless($request->status === AbsenceRequestStatus::Draft, 409, __('absence_requests.not_draft'));
    }
}
