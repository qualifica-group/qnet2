<?php

namespace App\Http\Resources;

use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderStatusResolver;
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
            'start_date' => $this->formatDate($this->start_date),
            'callback_date' => $this->formatDate($this->callback_date),
            'supervisors' => $this->summarizeUsers($this->supervisors),
            'participants' => $this->summarizeSlots($this->participants),
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'contract_number' => $this->quote?->code,
            'quote' => $this->summarizeQuote(),
            'quote_lines' => $this->summarizeQuoteLines(),
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
}
