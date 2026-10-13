<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\InvoiceTags\CreateInvoiceTagData;
use App\DataObjects\InvoiceTags\UpdateInvoiceTagData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\InvoiceTag;
use App\Services\Lookups\LookupOrderManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `invoice-tags` resource (spec 0247, D-5): a pure lookup
 * (no system row, no default: the tag of an invoice is optional), guarded only
 * by "in use by an invoice" on delete.
 */
class InvoiceTagService
{
    /** @var array<int, string> */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'color', 'is_active'];

    public function __construct(private readonly LookupOrderManager $orderManager) {}

    public function create(CreateInvoiceTagData $data): InvoiceTag
    {
        return DB::transaction(fn (): InvoiceTag => InvoiceTag::create([
            ...$data->attributes(),
            'sort_order' => $this->orderManager->placeNew(InvoiceTag::class),
        ]));
    }

    public function update(InvoiceTag $invoiceTag, UpdateInvoiceTagData $data): InvoiceTag
    {
        // Unconditional save: a clean save runs no UPDATE but still fires the
        // saved event the activity log and custom-fields pipeline listen to.
        $invoiceTag->fill($data->submittedAttributes())->save();

        return $invoiceTag->fresh();
    }

    /**
     * Restrictive delete: a tag still carried by an invoice cannot be removed
     * (409). The FK is also restrictOnDelete at the schema layer; the generic
     * bulk-delete goes through InvoiceTagsTableDefinition::deleteModel().
     */
    public function delete(InvoiceTag $invoiceTag): void
    {
        if ($invoiceTag->invoices()->exists()) {
            abort(409, __('invoice_tags.in_use'));
        }

        $invoiceTag->delete();
    }

    /**
     * Resequences every row to $orderedIds' order; see LookupOrderManager::reorder().
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, InvoiceTag>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, InvoiceTag> $reordered */
        $reordered = $this->orderManager->reorder(InvoiceTag::class, $orderedIds);

        return $reordered;
    }

    /**
     * Searchable, paginated tag list ordered by `sort_order`. Only active rows
     * unless `include_inactive`; the requested `ids[]` are appended even when
     * inactive so an invoice keeps showing its current tag.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = InvoiceTag::query()->select(self::FOR_SELECT_COLUMNS);

        if (! $query->includeInactive) {
            $base->where('is_active', true);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, InvoiceTag> $page */
        $page = $base->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @param  Collection<int, InvoiceTag>  $page
     * @return Collection<int, InvoiceTag>
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

        return $page->concat(
            InvoiceTag::query()
                ->select(self::FOR_SELECT_COLUMNS)
                ->whereIn('id', $missingIds)
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')
                ->get(),
        );
    }
}
