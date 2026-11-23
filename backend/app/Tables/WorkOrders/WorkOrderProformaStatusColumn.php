<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\ProformaRequestStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The "€" column of the commesse grids (spec 0407, legacy `OrdersTable` "€"):
 * the proforma state of the commessa, `none` (no request), `pending` (at least
 * one request still to issue) or `issued` (requests exist, none pending).
 * Shown, filtered and sorted only for the actors who may raise a request,
 * like the legacy column (Admin or "commessa importi"). The row value comes
 * from two EXISTS flags loaded with the page, never a query per row; filter
 * and sort run on the same rule, so cell, filter and order can never disagree.
 */
final class WorkOrderProformaStatusColumn
{
    public const string COLUMN = 'proforma_status';

    public const string NONE = 'none';

    public const string ENUM_KEY = 'work_order_proforma_status';

    private const string PERMISSION = 'proforma-requests.create';

    private const string RELATION = 'proformaRequests';

    private const string HAS_ANY_FLAG = 'has_proforma_request';

    private const string HAS_PENDING_FLAG = 'has_pending_proforma_request';

    /** Sort rank of a commessa with a pending request; any other request ranks after it, no request (NULL) before. */
    private const int PENDING_RANK = 1;

    private const int ISSUED_RANK = 2;

    public static function isVisibleTo(?User $actor): bool
    {
        return $actor?->can(self::PERMISSION) === true;
    }

    /**
     * Every value of the column, in the order of the workflow.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [self::NONE, ...ProformaRequestStatus::values()];
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function withFlags(Builder $query): void
    {
        $query->withExists([
            self::RELATION.' as '.self::HAS_ANY_FLAG,
            self::RELATION.' as '.self::HAS_PENDING_FLAG => static fn (Builder $requests) => $requests->where('status', ProformaRequestStatus::Pending->value),
        ]);
    }

    public function project(WorkOrder $workOrder): string
    {
        return match (true) {
            (bool) $workOrder->getAttribute(self::HAS_PENDING_FLAG) => ProformaRequestStatus::Pending->value,
            (bool) $workOrder->getAttribute(self::HAS_ANY_FLAG) => ProformaRequestStatus::Issued->value,
            default => self::NONE,
        };
    }

    /**
     * A commessa matches when its state is any of the picked ones; unknown
     * values are dropped, none left means no constraint.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        $statuses = array_values(array_intersect(self::values(), $values));

        if ($statuses === []) {
            return;
        }

        $query->where(function (Builder $group) use ($statuses): void {
            foreach ($statuses as $status) {
                $group->orWhere(fn (Builder $match) => $this->constrainTo($match, $status));
            }
        });
    }

    /**
     * Ascending: none, pending, issued (the NULL rank of "no request" sorts
     * first on both MySQL and SQLite).
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->rankSubquery(), $direction);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function constrainTo(Builder $query, string $status): void
    {
        $pending = static fn (Builder $requests) => $requests->where('status', ProformaRequestStatus::Pending->value);

        match ($status) {
            self::NONE => $query->whereDoesntHave(self::RELATION),
            ProformaRequestStatus::Pending->value => $query->whereHas(self::RELATION, $pending),
            default => $query->whereHas(self::RELATION)->whereDoesntHave(self::RELATION, $pending),
        };
    }

    private function rankSubquery(): QueryBuilder
    {
        return DB::table('proforma_requests')
            ->selectRaw('MIN(CASE WHEN status = ? THEN ? ELSE ? END)', [
                ProformaRequestStatus::Pending->value,
                self::PENDING_RANK,
                self::ISSUED_RANK,
            ])
            ->whereColumn('proforma_requests.work_order_id', 'work_orders.id');
    }
}
