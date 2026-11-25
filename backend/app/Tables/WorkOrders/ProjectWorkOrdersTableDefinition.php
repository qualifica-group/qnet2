<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use App\Services\WorkOrders\Chat\WorkOrderChatUnreadQuery;
use App\Services\WorkOrders\WorkOrderTypeAccess;
use App\Tables\WorkOrdersTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `project-work-orders` domain (spec 0359, D-1):
 * the `work-orders` grid (same columns, actions, allow-lists and visibility
 * scope, all inherited) restricted to `type = project`, plus the read-only
 * `chat_unread_count` column. Its own module (user decision 2026-11-25): the
 * list needs `project-work-orders.viewAny`, the actions that open the record
 * `project-work-orders.view` (WorkOrderTypeAccess).
 *
 * `chat_unread_count` is `WorkOrderChatUnreadQuery`, one correlated
 * subquery, never a query per row; the sort uses the selected alias, so it
 * needs no raw SQL.
 */
class ProjectWorkOrdersTableDefinition extends WorkOrdersTableDefinition
{
    public const string UNREAD_COLUMN = 'chat_unread_count';

    public function domain(): string
    {
        return 'project-work-orders';
    }

    /**
     * Field catalogue, inline-edit rights and the non-viewing permissions are
     * the work orders' own.
     */
    public function resource(): string
    {
        return 'work-orders';
    }

    public function authorizeViewAny(User $actor): bool
    {
        return $actor->can(WorkOrderTypeAccess::viewAnyPermission(WorkOrderType::Project));
    }

    /**
     * The inherited catalogue, with the "Commesse" viewing permission of the
     * actions that open the record swapped for this module's own.
     *
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        $processingView = WorkOrderTypeAccess::viewPermission(WorkOrderType::Processing);

        return array_map(static fn (array $action): array => ($action['permission'] ?? null) === $processingView
            ? [...$action, 'permission' => WorkOrderTypeAccess::viewPermission(WorkOrderType::Project)]
            : $action, parent::actions());
    }

    /**
     * @return Builder<WorkOrder>
     */
    public function baseQuery(): Builder
    {
        $query = parent::baseQuery()->where('work_orders.type', WorkOrderType::Project->value);

        $actor = Auth::user();

        // No "Non letti" with the chat switched off (CHAT_ENABLED, spec 0432).
        return $actor === null || ! WorkOrderChatAccess::isEnabled()
            ? $query
            : $query->selectSub(app(WorkOrderChatUnreadQuery::class)->forActor($actor), self::UNREAD_COLUMN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        if (! WorkOrderChatAccess::isEnabled()) {
            return parent::columns();
        }

        return [
            ...parent::columns(),
            [
                'id' => self::UNREAD_COLUMN,
                'label' => 'workOrders.columns.chat_unread_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
                'hasFilterValues' => false,
            ],
        ];
    }

    /**
     * The personal view belongs to the "Commesse" page alone.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return WorkOrderAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        $mapped = parent::mapRow($actor, $row);

        return WorkOrderChatAccess::isEnabled()
            ? [...$mapped, self::UNREAD_COLUMN => (int) ($row->getAttribute(self::UNREAD_COLUMN) ?? 0)]
            : $mapped;
    }
}
