<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\WorkOrderPaymentStatuses\CreateWorkOrderPaymentStatusData;
use App\DataObjects\WorkOrderPaymentStatuses\UpdateWorkOrderPaymentStatusData;
use App\Models\WorkOrderPaymentStatus;
use App\Services\WorkOrderPaymentStatuses\WorkOrderPaymentStatusOrderManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic of the `work-order-payment-statuses` lookup (spec 0201, D-2):
 * a full-CRUD catalogue (name/description/color/is_active/allows_delivery) of
 * the payment statuses a commessa line can be in. `sort_order` is
 * server-managed by WorkOrderPaymentStatusOrderManager.
 */
class WorkOrderPaymentStatusService
{
    /** Columns the for-select projection reads (see WorkOrderPaymentStatusForSelectResource). */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'color', 'allows_delivery'];

    public function __construct(private readonly WorkOrderPaymentStatusOrderManager $orderManager) {}

    public function create(CreateWorkOrderPaymentStatusData $data): WorkOrderPaymentStatus
    {
        return WorkOrderPaymentStatus::create([...$data->attributes(), 'sort_order' => $this->orderManager->placeNew()]);
    }

    public function update(WorkOrderPaymentStatus $status, UpdateWorkOrderPaymentStatusData $data): WorkOrderPaymentStatus
    {
        $status->fill($data->submittedAttributes())->save();

        return $status->fresh();
    }

    /**
     * A status still assigned to a commessa line cannot be removed (it would
     * silently blank the payment state); the FK is also RESTRICT.
     */
    public function delete(WorkOrderPaymentStatus $status): void
    {
        if ($status->linePayments()->exists()) {
            abort(409, 'This work order payment status is used by a work order line and cannot be deleted.');
        }

        $status->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, WorkOrderPaymentStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, WorkOrderPaymentStatus> $reordered */
        $reordered = $this->orderManager->reorder($orderedIds);

        return $reordered;
    }

    /**
     * Searchable, paginated list for the for-select standard (ADR 0011): only
     * active rows, in table order. Explicitly requested `ids[]` (edit-mode
     * hydration) bypass search and the active filter so a deactivated status
     * keeps showing on the line that holds it.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = WorkOrderPaymentStatus::query()->select(self::FOR_SELECT_COLUMNS)->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, WorkOrderPaymentStatus> $page */
        $page = $this->ordered($base)->offset($query->offset)->limit($query->limit)->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @param  Collection<int, WorkOrderPaymentStatus>  $page
     * @return Collection<int, WorkOrderPaymentStatus>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        $hydrated = $this->ordered(
            WorkOrderPaymentStatus::query()->select(self::FOR_SELECT_COLUMNS)->whereIn('id', $missingIds),
        )->get();

        return $page->concat($hydrated);
    }

    /**
     * @param  Builder<WorkOrderPaymentStatus>  $builder
     * @return Builder<WorkOrderPaymentStatus>
     */
    private function ordered(Builder $builder): Builder
    {
        return $builder->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }
}
