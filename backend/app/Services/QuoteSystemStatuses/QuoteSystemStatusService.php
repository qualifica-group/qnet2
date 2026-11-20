<?php

declare(strict_types=1);

namespace App\Services\QuoteSystemStatuses;

use App\DataObjects\QuoteSystemStatuses\QuoteSystemStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\QuoteSystemStatus;
use App\Models\QuoteWorkflowStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic of the quote system statuses configurator (spec 0404 part
 * A): CRUD, reorder and for-select, on the same order/guard services as the
 * contract statuses. A behavior change propagates to the `group` of every
 * linked working status in the same transaction (A-D2), with no retroactive
 * effect on the offers already there (no contract created or suspended).
 */
final class QuoteSystemStatusService
{
    private const int CONFLICT = 409;

    private const string NAME_SEPARATOR = ', ';

    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    public function loadDetail(QuoteSystemStatus $status): QuoteSystemStatus
    {
        return $status->loadCount('workflowStatuses');
    }

    public function create(QuoteSystemStatusData $data): QuoteSystemStatus
    {
        return DB::transaction(function () use ($data): QuoteSystemStatus {
            $status = QuoteSystemStatus::query()->create([
                ...$data->submittedAttributes(),
                'sort_order' => $this->orderManager->placeNew(QuoteSystemStatus::class),
            ]);

            return $this->loadDetail($status);
        });
    }

    public function update(QuoteSystemStatus $status, QuoteSystemStatusData $data): QuoteSystemStatus
    {
        return DB::transaction(function () use ($status, $data): QuoteSystemStatus {
            // Step 1: an unchanged behavior is not a change (the form resends it).
            $attributes = $data->submittedAttributes();

            if ($data->behavior() === $status->behavior->value) {
                unset($attributes['behavior']);
            }

            // Step 2: a system row keeps its behavior (A-D3).
            $this->systemStatusGuard->assertUpdatable($status, $attributes);

            // Step 3: persist, then copy a new behavior onto the linked working statuses.
            $status->fill($attributes)->save();

            if (array_key_exists('behavior', $attributes)) {
                QuoteWorkflowStatus::query()
                    ->where('quote_system_status_id', $status->id)
                    ->update(['group' => $status->behavior->value]);
            }

            return $this->loadDetail($status->fresh());
        });
    }

    /**
     * A system row is never deletable; a linked one is refused with the names
     * of the working statuses that link it, so the user can relink them first.
     */
    public function delete(QuoteSystemStatus $status): void
    {
        $this->systemStatusGuard->assertDeletable($status);

        $linkedNames = $status->workflowStatuses()->orderBy('name')->distinct()->pluck('name');

        if ($linkedNames->isNotEmpty()) {
            abort(self::CONFLICT, __('This system status is linked to working statuses and cannot be deleted: :statuses', [
                'statuses' => $linkedNames->implode(self::NAME_SEPARATOR),
            ]));
        }

        $status->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, QuoteSystemStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, QuoteSystemStatus> $reordered */
        $reordered = $this->orderManager->reorder(QuoteSystemStatus::class, $orderedIds);

        return $reordered;
    }

    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = QuoteSystemStatus::query()
            ->when($query->hasSearch(), fn ($builder) => $builder->where('name', 'like', '%'.$query->search.'%'));

        $total = (clone $base)->count();

        $page = $base->orderBy('sort_order')->orderBy('id')->offset($query->offset)->limit($query->limit)->get();

        if ($query->hasIds()) {
            $missing = array_values(array_diff($query->ids, $page->pluck('id')->all()));
            $page = $page->concat(QuoteSystemStatus::query()->whereIn('id', $missing)->orderBy('sort_order')->get());
        }

        return new ForSelectResult(items: $page, total: $total, offset: $query->offset, limit: $query->limit);
    }
}
