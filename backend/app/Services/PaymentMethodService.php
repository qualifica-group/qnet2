<?php

namespace App\Services;

use App\DataObjects\PaymentMethods\CreatePaymentMethodData;
use App\DataObjects\PaymentMethods\UpdatePaymentMethodData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\PaymentMethod;
use App\Services\PaymentMethods\PaymentMethodOrderManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `payment-methods` resource (spec 0068): a full-CRUD,
 * consumer-agnostic lookup (name/code/description/payment_instructions/
 * payment_days/is_active) describing a payment modality. `sort_order` is
 * server-managed — placed by PaymentMethodOrderManager::placeNew() on
 * create, resequenced by reorder().
 */
class PaymentMethodService
{
    public function __construct(private readonly PaymentMethodOrderManager $orderManager) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other lookup services even though a PaymentMethod has no
     * relation to eager-load in this iteration.
     */
    public function loadDetail(PaymentMethod $paymentMethod): PaymentMethod
    {
        return $paymentMethod;
    }

    public function create(CreatePaymentMethodData $data): PaymentMethod
    {
        $sortOrder = $this->orderManager->placeNew();

        $paymentMethod = PaymentMethod::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($paymentMethod);
    }

    public function update(PaymentMethod $paymentMethod, UpdatePaymentMethodData $data): PaymentMethod
    {
        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $paymentMethod->fill($data->submittedAttributes())->save();

        return $this->loadDetail($paymentMethod->fresh());
    }

    /**
     * Plain delete, no guard (spec 0068, D-2): no module consumes
     * `payment-methods` yet in this iteration, so there is no relation to
     * protect. The extension point for the first real consumer (a
     * referenced-by guard mirroring RewardStatusService::delete()) is
     * documented here rather than implemented ahead of a consumer.
     */
    public function delete(PaymentMethod $paymentMethod): void
    {
        $paymentMethod->delete();
    }

    /**
     * Resequences every row to $orderedIds' order and returns the fresh,
     * complete, ordered list (D-1). See PaymentMethodOrderManager::reorder()
     * for the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, PaymentMethod>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, PaymentMethod> $reordered */
        $reordered = $this->orderManager->reorder($orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated payment method list for the for-select
     * standard (ADR 0011). Only `is_active = true` rows are eligible,
     * ordered by `sort_order` first so the select mirrors the table's
     * display order.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = PaymentMethod::query()->select(['id', 'name', 'code', 'payment_days'])->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, PaymentMethod> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search AND the
     * `is_active` filter (a consumer keeps showing its currently assigned
     * payment method even after it is deactivated), same projection applies.
     * Total is unaffected.
     *
     * @param  Collection<int, PaymentMethod>  $page
     * @return Collection<int, PaymentMethod>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, PaymentMethod> $hydrated */
        $hydrated = PaymentMethod::query()
            ->select(['id', 'name', 'code', 'payment_days'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
