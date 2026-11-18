<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * The records whose creation and status changes reach the actor's org-chart
 * managers holding `<resource>.notifyTeam` (spec 0413): one case per module,
 * with the permission, the status it follows and the link it opens.
 */
enum TeamActivityRecordEnum: string
{
    /** The ability each module's policy declares (QuotePolicy, ContractPolicy, WorkOrderPolicy). */
    public const string ABILITY = 'notifyTeam';

    case Quote = 'quote';
    case Contract = 'contract';
    case WorkOrder = 'work_order';

    public static function of(Model $record): self
    {
        return match (true) {
            $record instanceof Quote => self::Quote,
            $record instanceof Contract => self::Contract,
            $record instanceof WorkOrder => self::WorkOrder,
        };
    }

    /**
     * Every `notifyTeam` permission: a notification choice, never part of a
     * whole-module grant (admin, mansioni), or every manager would be paged.
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(static fn (self $record): string => $record->permission(), self::cases());
    }

    public function permission(): string
    {
        return match ($this) {
            self::Quote => 'quotes.',
            self::Contract => 'contracts.',
            self::WorkOrder => 'work-orders.',
        }.self::ABILITY;
    }

    /** The column holding the status the notification follows. */
    public function statusColumn(): string
    {
        return match ($this) {
            self::Quote => 'quote_workflow_status_id',
            self::Contract => 'contract_status_id',
            self::WorkOrder => 'work_order_workflow_status_id',
        };
    }

    public function statusName(?int $statusId): ?string
    {
        if ($statusId === null) {
            return null;
        }

        $model = match ($this) {
            self::Quote => QuoteWorkflowStatus::class,
            self::Contract => ContractStatus::class,
            self::WorkOrder => WorkOrderWorkflowStatus::class,
        };

        return $model::query()->whereKey($statusId)->value('name');
    }

    /** The SPA path THIS recipient may open, null when no module of theirs opens it. */
    public function pathFor(User $notifiable, int $recordId): ?string
    {
        return match ($this) {
            self::Quote => RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::Quote, $recordId),
            self::WorkOrder => RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::WorkOrder, $recordId),
            self::Contract => $notifiable->can('contracts.view') ? "/contracts/{$recordId}" : null,
        };
    }
}
