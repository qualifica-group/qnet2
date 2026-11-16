<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\WorkOrders\RequestWorkOrderUpdateData;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderUpdateRequested;
use Illuminate\Support\Facades\Notification;

/**
 * "Richiedi aggiornamento" on a work order (spec 0352, clone of the task
 * flow, spec 0153 D-14): no transaction and no write on the record, only
 * notifications. The actor is never excluded from the recipients.
 */
class WorkOrderUpdateRequester
{
    public const string TARGET_PARTICIPANTS = 'participants';

    public const string TARGET_SUPERVISORS = 'supervisors';

    public const string TARGET_ALL = 'all';

    public const array TARGETS = [self::TARGET_PARTICIPANTS, self::TARGET_SUPERVISORS, self::TARGET_ALL];

    public function handle(WorkOrder $workOrder, RequestWorkOrderUpdateData $data, User $actor): void
    {
        // Step 1: only open / in progress work orders can be asked for an update
        $this->assertAvailable($workOrder);

        // Step 2: resolve the direct recipients and the copy group
        [$recipientIds, $ccIds] = $this->resolveRecipients($workOrder, $data->target);

        // Step 3: direct and copy are separate notifications (is_cc differs)
        $this->send($workOrder, $actor, $data->message, $recipientIds, isCc: false);
        $this->send($workOrder, $actor, $data->message, $ccIds, isCc: true);
    }

    /**
     * Recipients of a target, copy excluded (used by the request to refuse an empty group).
     *
     * @return array<int, int>
     */
    public function recipientIdsFor(WorkOrder $workOrder, string $target): array
    {
        return $this->resolveRecipients($workOrder, $target)[0];
    }

    private function assertAvailable(WorkOrder $workOrder): void
    {
        if (! $workOrder->work_phase->isOngoing()) {
            abort(422, 'This work order is already completed or closed.');
        }
    }

    /**
     * `participants` copies the supervisors who are not participants;
     * `supervisors` and `all` send no copy.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function resolveRecipients(WorkOrder $workOrder, string $target): array
    {
        $participantIds = $workOrder->participants()->pluck('users.id')->map(intval(...))->all();
        $supervisorIds = $workOrder->supervisors()->pluck('users.id')->map(intval(...))->all();

        return match ($target) {
            self::TARGET_SUPERVISORS => [$supervisorIds, []],
            self::TARGET_ALL => [array_values(array_unique([...$participantIds, ...$supervisorIds])), []],
            default => [$participantIds, array_values(array_diff($supervisorIds, $participantIds))],
        };
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function send(WorkOrder $workOrder, User $actor, string $message, array $userIds, bool $isCc): void
    {
        if ($userIds === []) {
            return;
        }

        Notification::send(
            User::query()->whereIn('id', $userIds)->get(),
            new WorkOrderUpdateRequested($workOrder, $actor, $message, $isCc),
        );
    }
}
