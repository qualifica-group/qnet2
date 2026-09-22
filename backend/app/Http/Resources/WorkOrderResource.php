<?php

namespace App\Http\Resources;

use App\Enums\FormMode;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use App\Services\WorkOrders\WorkOrderTaskForceCloser;
use App\WorkOrders\WorkOrderAttributeResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderDetail shape (spec 0093 data_contract). `status` is ALWAYS
 * computed via WorkOrderStatusResolver (D-3), never read off a column.
 * `contract_number` is `quote.code`, derived and read-only (D-2) — never a
 * column of its own. `quote_lines` composes its label live from
 * `product.code`/`product.name` (D-8, spec 0065 D-7's own precedent).
 *
 * Spec 0098 (frozen api-contract): `attribute_values` is the raw work-order
 * -level values map (`{}` when null); `applicable_attributes` is the union/
 * dedup-by-code set of THIS work order's own `quote_lines`' effective
 * category attributes (App\WorkOrders\WorkOrderAttributeResolver, context
 * `work_order`); `attribute_layout` completes the trio with the merged,
 * multi-category layout (spec 0062), `FormMode::View` since this resource IS
 * the detail's read-only render. Relies on
 * WorkOrderService::DETAIL_RELATIONS already eager-loading
 * `quoteLines.product.category`, so resolving all three never N+1s.
 *
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = app(WorkOrderStatusResolver::class)->resolve($this->resource);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'type' => $this->type?->value,
            'status' => [
                'value' => $status->value,
                'is_force_closed' => $this->is_force_closed,
            ],
            'is_force_closed' => $this->is_force_closed,
            'force_close_reason' => $this->force_close_reason,
            // Spec 0146, D-8/AC-023: the tasks a force-close would touch
            // right now, counted WITHOUT TaskVisibilityScope — the same
            // query WorkOrderTaskForceCloser::closeOpenTasks() runs.
            'open_tasks_count' => app(WorkOrderTaskForceCloser::class)->countOpenTasks($this->resource),
            'start_date' => $this->formatDate($this->start_date),
            'callback_date' => $this->formatDate($this->callback_date),
            'supervisors' => $this->summarizeUsers($this->supervisors),
            'participants' => $this->summarizeSlots($this->participants),
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'contract_number' => $this->quote?->code,
            'task_template' => $this->summarizeTaskTemplate(),
            'quote' => $this->summarizeQuote(),
            'contract' => $this->summarizeContract(),
            'quote_lines' => $this->summarizeQuoteLines(),
            // Cast to object, non array: un array PHP vuoto serializza come
            // `[]`, e il form legge la chiave come una MAPPA (Zod
            // `z.object`) — con `[]` la validazione fallisce su un campo che
            // nessun input rende (stesso precedente di QuoteResource).
            'attribute_values' => (object) ($this->attribute_values ?? []),
            'applicable_attributes' => $this->resolveApplicableAttributes(),
            'attribute_layout' => app(WorkOrderAttributeResolver::class)->layout($this->resource, FormMode::View),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * `Y-m-d`, the shape the data_contract declares and the shape an
     * `<input type="date">` accepts.
     *
     * The `date:Y-m-d` cast alone is NOT enough here: it governs the MODEL's
     * own serialization, while a Resource hands the raw CarbonImmutable to
     * json_encode, which renders it as a full ISO-8601 timestamp. Spec 0093
     * shipped `callback_date` that way, so the edit form showed an empty
     * "Data richiamo" for a commessa that had one — a real bug, found by
     * spec 0096's own date assertions and fixed here for both columns rather
     * than left to bite the new required field too.
     */
    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * The Responsabili (spec 0096, D-1): a plain set, no pivot metadata —
     * ordered by name at the relation, not by any stored rank.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeUsers(Collection $users): array
    {
        return $users->map(static fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
        ])->all();
    }

    /**
     * The ordered Partecipanti (spec 0096, D-3), byte-identical to
     * QuoteResource::summarizeManagers(): `position` is the 1-based slot the
     * shared ManagerSlotsField reconstructs its gaps from.
     *
     * @param  Collection<int, User>  $participants
     * @return array<int, array{id: int, name: string, position: int}>
     */
    private function summarizeSlots(Collection $participants): array
    {
        return $participants->map(static fn (User $participant): array => [
            'id' => $participant->id,
            'name' => $participant->name,
            'position' => (int) $participant->pivot->position,
        ])->all();
    }

    /**
     * The Modello di Task this commessa was generated from (spec 0124,
     * D-9): null for the majority of commesse, generated without one
     * (AC-020) — the frozen `{ id, name }` shape, never the full
     * TaskTemplate.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeTaskTemplate(): ?array
    {
        $template = $this->taskTemplate;

        if ($template === null) {
            return null;
        }

        return ['id' => $template->id, 'name' => $template->name];
    }

    /**
     * @return array{id: int, code: string, title: string}|null
     */
    private function summarizeQuote(): ?array
    {
        $quote = $this->quote;

        if ($quote === null) {
            return null;
        }

        return ['id' => $quote->id, 'code' => $quote->code, 'title' => $quote->title];
    }

    /**
     * The Contratto born from the linked quote (spec 0072: one per quote,
     * `code`/`title` are the quote's own, as on the Contract detail), `null`
     * while the quote has not been won. The detail names this record, not the
     * offer underneath it (user directive 2026-09-16).
     *
     * @return array{id: int, code: string, title: string}|null
     */
    private function summarizeContract(): ?array
    {
        $contract = $this->quote?->contract;

        if ($contract === null) {
            return null;
        }

        return ['id' => $contract->id, 'code' => $this->quote->code, 'title' => $this->quote->title];
    }

    /**
     * @return array<int, array{id: int, sort_order: int, product: array{id: int, code: string, name: string}|null}>
     */
    private function summarizeQuoteLines(): array
    {
        /** @var Collection<int, QuoteLine> $lines */
        $lines = $this->quoteLines;

        return $lines->map(static function ($line): array {
            $product = $line->product;

            return [
                'id' => $line->id,
                'sort_order' => $line->sort_order,
                'product' => $product === null ? null : ['id' => $product->id, 'code' => $product->code, 'name' => $product->name],
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveApplicableAttributes(): array
    {
        return app(WorkOrderAttributeResolver::class)
            ->resolve($this->resource)
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }
}
