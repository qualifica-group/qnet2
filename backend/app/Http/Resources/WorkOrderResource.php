<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Enums\FormMode;
use App\Http\Resources\Concerns\SummarizesProductLines;
use App\Http\Resources\Concerns\SummarizesRewards;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\Services\WorkOrders\WorkOrderSourceQuotes;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use App\Services\WorkOrders\WorkOrderTaskForceCloser;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
use App\Support\OperationalSiteLabel;
use App\Support\ReferentRef;
use App\WorkOrders\WorkOrderAttributeResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderDetail shape (spec 0093 data_contract). `status` and
 * `completion_percentage` (+ `completion_recorded_on`) are ALWAYS computed via
 * WorkOrderStatusResolver (spec 0321, manual progress entries), never read off a column.
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
    use SummarizesProductLines;
    use SummarizesRewards;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = app(WorkOrderStatusResolver::class)->progress($this->resource);

        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'code' => $this->code,
            'title' => $this->title,
            'title_is_manual' => $this->title_is_manual,
            'type' => $this->type?->value,
            'status' => [
                'value' => $progress->status->value,
                'is_force_closed' => $this->is_force_closed,
            ],
            'work_order_workflow_status_id' => $this->work_order_workflow_status_id,
            'work_order_workflow_status' => $this->summarizeWorkflowStatus(),
            'work_order_workflow_statuses' => $this->resolvedWorkflowStatuses(),
            'completion_percentage' => $progress->completionPercentage,
            'completion_recorded_on' => $progress->recordedOn,
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
            'is_multi_quote' => $this->isMultiQuote(),
            'source_quotes' => $this->summarizeSourceQuotes(),
            'quote' => $this->summarizeQuote(),
            'contract' => $this->summarizeContract(),
            'registry' => $this->summarizeRegistry(),
            'company' => $this->summarizeCompany(),
            'company_site' => $this->summarizeCompanySite(),
            'operational_site' => OperationalSiteLabel::summarize($this->quote?->operationalSite),
            // Spec 0331: read-only projection of the single offer; a multi-offer
            // commessa has no `quote`, so these are null / [].
            'referent' => ReferentRef::summarize($this->quote?->opportunity?->referent),
            'commercial' => $this->summarizeByName($this->quote?->commercial),
            'reporter' => $this->summarizeByName($this->quote?->reporter),
            'source' => $this->summarizeByName($this->quote?->source),
            'product_lines' => $this->summarizeProductLines($this->quote?->opportunity?->productLines ?? []),
            'rewards' => $this->summarizeRewards($this->quote?->rewards ?? []),
            'layout' => $this->summarizeByName($this->quote?->layout),
            'payment_method' => $this->summarizeByName($this->quote?->paymentMethod),
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
     * The "Stato lavorazione" (spec 0322, D-1), distinct from the computed `status`.
     *
     * @return array{id: int, name: string, color: string|null, group: string, system_key: string|null, progress_percentage: int|null, description: string|null}|null
     */
    private function summarizeWorkflowStatus(): ?array
    {
        $status = $this->workOrderWorkflowStatus;

        if ($status === null) {
            return null;
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
            'system_key' => $status->system_key,
            'progress_percentage' => $status->progress_percentage,
            'description' => $status->description,
        ];
    }

    /**
     * The status set resolved for THIS commessa (spec 0322, D-8), from its
     * `quoteLines.product` the detail already eager-loads.
     *
     * @return array<int, mixed>
     */
    private function resolvedWorkflowStatuses(): array
    {
        $resolver = app(WorkOrderWorkflowResolver::class);

        return WorkOrderWorkflowStatusResource::collection(
            $resolver->statusesFor($resolver->resolve($this->resource)),
        )->resolve();
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
     * The offers this commessa draws from (spec 0267, D-7): one element on a
     * mono-offer commessa, the distinct offers of its lines on a multi-offer
     * one. Ordered by code.
     *
     * @return array<int, array{id: int, code: string, title: string|null, contract: array{id: int}|null, registry: array{id: int, name: string}|null, company: array{id: int, name: string}|null}>
     */
    private function summarizeSourceQuotes(): array
    {
        return app(WorkOrderSourceQuotes::class)->forDetail($this->resource)
            ->map(static function (Quote $quote): array {
                $registry = $quote->opportunity?->registry;
                $company = $quote->company;

                return [
                    'id' => $quote->id,
                    'code' => $quote->code,
                    'title' => $quote->title,
                    'contract' => $quote->contract === null ? null : ['id' => $quote->contract->id],
                    'registry' => $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name],
                    'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->denomination],
                ];
            })
            ->values()
            ->all();
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
     * offer underneath it (user directive 2026-09-16). `expiry_date` is the
     * contract's own, shown read-only on the commessa.
     *
     * @return array{id: int, code: string, title: string, expiry_date: string|null}|null
     */
    private function summarizeContract(): ?array
    {
        $contract = $this->quote?->contract;

        if ($contract === null) {
            return null;
        }

        return [
            'id' => $contract->id,
            'code' => $this->quote->code,
            'title' => $this->quote->title,
            'expiry_date' => $this->formatDate($contract->expiry_date),
        ];
    }

    /**
     * The client of the commessa, reached through `quote.opportunity` (a work
     * order has no `registry_id` of its own). Spec 0173: the detail mounts its
     * documents read-only.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeRegistry(): ?array
    {
        $registry = $this->quote?->opportunity?->registry;

        if ($registry === null) {
            return null;
        }

        return ['id' => $registry->id, 'name' => $registry->name];
    }

    /**
     * The Societa' of the linked quote, the same live projection the Contract
     * detail shows (ContractResource::summarizeCompany()): `companies` has no
     * `name` column, its display name IS `denomination`.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeCompany(): ?array
    {
        $company = $this->quote?->company;

        return $company === null ? null : ['id' => $company->id, 'name' => $company->denomination];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeCompanySite(): ?array
    {
        $site = $this->quote?->companySite;

        return $site === null ? null : ['id' => $site->id, 'name' => $site->name];
    }

    /**
     * `registry` is the client of the line's own offer (spec 0267): on a
     * multi-offer commessa it tells the lines apart, in place of the single
     * `registry` of the resource.
     *
     * @return array<int, array{id: int, sort_order: int, product: array{id: int, code: string, name: string}|null, registry: array{id: int, name: string}|null}>
     */
    private function summarizeQuoteLines(): array
    {
        /** @var Collection<int, QuoteLine> $lines */
        $lines = $this->quoteLines;

        return $lines->map(static function ($line): array {
            $product = $line->product;
            $registry = $line->quote?->opportunity?->registry;

            return [
                'id' => $line->id,
                'sort_order' => $line->sort_order,
                'product' => $product === null ? null : ['id' => $product->id, 'code' => $product->code, 'name' => $product->name],
                'registry' => $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name],
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
