<?php

namespace App\Services;

use App\DataObjects\QuoteStatuses\CreateQuoteStatusData;
use App\DataObjects\QuoteStatuses\UpdateQuoteStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\QuoteStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `quote-statuses` resource (spec 0065): a plain
 * clone of OpportunityStatusService — a full-CRUD lookup entity
 * (name/color) describing a Quote's working state.
 *
 * `sort_order` is server-managed — placed by StatusOrderManager::placeNew()
 * on create, resequenced by reorder(); the three mandatory system rows
 * ("Bozza"/"Accettata"/"Rifiutata", D-2) are protected by SystemStatusGuard
 * on both update() and delete().
 */
class QuoteStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other status services even though `group` is a plain
     * column, needing no eager-load.
     */
    public function loadDetail(QuoteStatus $quoteStatus): QuoteStatus
    {
        return $quoteStatus;
    }

    public function create(CreateQuoteStatusData $data): QuoteStatus
    {
        $sortOrder = $this->orderManager->placeNew(QuoteStatus::class);

        $quoteStatus = QuoteStatus::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($quoteStatus);
    }

    public function update(QuoteStatus $quoteStatus, UpdateQuoteStatusData $data): QuoteStatus
    {
        $attributes = $data->submittedAttributes();

        $this->systemStatusGuard->assertUpdatable($quoteStatus, $attributes);

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $quoteStatus->fill($attributes)->save();

        return $this->loadDetail($quoteStatus->fresh());
    }

    /**
     * A status referenced by at least one Quote cannot be removed (it would
     * silently orphan them). Defense in depth: the FK is also restrictOnDelete
     * at the schema layer. The system-row guard (spec 0065, D-2) runs FIRST:
     * a system row is never deletable regardless of whether it happens to be
     * unreferenced.
     */
    public function delete(QuoteStatus $quoteStatus): void
    {
        $this->systemStatusGuard->assertDeletable($quoteStatus);

        if ($quoteStatus->quotes()->exists()) {
            abort(409, 'This quote status is used by a quote and cannot be deleted.');
        }

        $quoteStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list. See StatusOrderManager::reorder() for
     * the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, QuoteStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, QuoteStatus> $reordered */
        $reordered = $this->orderManager->reorder(QuoteStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated quote status list for the for-select
     * standard (ADR 0011). Ordered by `sort_order` first so the select
     * mirrors the table's display order.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = QuoteStatus::query()->select(['id', 'name', 'system_key']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, QuoteStatus> $page */
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
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, QuoteStatus>  $page
     * @return Collection<int, QuoteStatus>
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

        /** @var Collection<int, QuoteStatus> $hydrated */
        $hydrated = QuoteStatus::query()
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
