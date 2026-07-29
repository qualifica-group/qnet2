<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 *
 * Shape frozen by spec 0065's data_contract (GET/POST/PATCH /api/quotes...,
 * MT-05). Relies on QuoteService::loadDetail()/DETAIL_RELATIONS having
 * eager-loaded `opportunity`, `quoteStatus`, `commercial`, `reporter`,
 * `supervisor`, `offerLines.product.category`, `offerLines.vatRate`,
 * `costLines.product.category`, `costLines.vatRate`, so resolving any of them
 * here never N+1s (per-line product/category/business-function resolution is
 * QuoteLineResource's own concern — see its docblock).
 *
 * `summary.*.gross` is DERIVED here (net + vat) at request time — NEVER
 * persisted (D-9): the 5 persisted aggregates (`revenue_net`, `revenue_vat`,
 * `cost_net`, `cost_vat`, `margin_net`) are the only source of truth this
 * resource reads from.
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'opportunity_id' => $this->opportunity_id,
            'opportunity' => $this->summarizeByName($this->opportunity),
            'quote_status_id' => $this->quote_status_id,
            'quote_status' => $this->summarizeStatus($this->quoteStatus),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->summarizeByName($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->summarizeByName($this->reporter),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->summarizeByName($this->supervisor),
            'internal_notes' => $this->internal_notes,
            'offer_lines' => QuoteLineResource::collection($this->offerLines),
            'cost_lines' => QuoteLineResource::collection($this->costLines),
            'summary' => $this->summarizeTotals(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, name: string, color: string|null, group: string}|null
     */
    private function summarizeStatus(?Model $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'group' => $status->group->value,
        ];
    }

    /**
     * D-5: revenue/cost side by side, margin computed on the net (revenue
     * net minus cost net, persisted as `margin_net` — may be negative,
     * AC-043, never clamped).
     *
     * @return array{revenue: array{net: string, vat: string, gross: string}, cost: array{net: string, vat: string, gross: string}, margin: array{net: string}}
     */
    private function summarizeTotals(): array
    {
        return [
            'revenue' => $this->amountTriplet($this->revenue_net, $this->revenue_vat),
            'cost' => $this->amountTriplet($this->cost_net, $this->cost_vat),
            'margin' => ['net' => $this->margin_net],
        ];
    }

    /**
     * `gross` is DERIVED at runtime (net + vat), never persisted (D-9).
     *
     * @return array{net: string, vat: string, gross: string}
     */
    private function amountTriplet(string $net, string $vat): array
    {
        return [
            'net' => $net,
            'vat' => $vat,
            'gross' => number_format((float) $net + (float) $vat, 2, '.', ''),
        ];
    }
}
