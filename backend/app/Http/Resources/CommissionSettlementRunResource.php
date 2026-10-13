<?php

namespace App\Http\Resources;

use App\Services\Commissions\Settlements\SettlementRunView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a settlement run (spec 0213, D-6): the run, the settlements it
 * created and the pairs it left out with their reason. The balance columns
 * (`taxable_amount`, `net_payable`) honour the field permissions of the
 * `commission-settlements` resource.
 *
 * @property SettlementRunView $resource
 */
class CommissionSettlementRunResource extends JsonResource
{
    /**
     * @param  array<string, array{visible?: bool}>  $fieldPermissions
     */
    public function __construct(SettlementRunView $resource, private readonly array $fieldPermissions = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $run = $view->run;

        return [
            'run' => [
                'id' => $run->id,
                'period_from' => $run->period_from->format('Y-m-d'),
                'period_to' => $run->period_to->format('Y-m-d'),
                'filters' => $run->filters,
                'min_amount' => $run->min_amount,
                'settlements_count' => $run->settlements_count,
                'created_at' => $run->created_at,
                'created_by' => $run->creator === null ? null : ['id' => $run->creator->id, 'name' => $run->creator->name],
            ],
            'created' => $view->settlements->map(fn ($settlement): array => $this->visible([
                'settlement_id' => $settlement->id,
                'referent' => $this->party($view->referentNames, $settlement->referent_id),
                'company' => $this->party($view->companyNames, $settlement->company_id),
                'taxable_amount' => $settlement->taxable_amount,
                'net_payable' => $settlement->net_payable,
            ]))->values()->all(),
            'skipped' => collect($run->skipped ?? [])->map(fn (array $entry): array => $this->visible([
                'referent' => $this->party($view->referentNames, $entry['referent_id']),
                'company' => $this->party($view->companyNames, $entry['company_id']),
                'reason' => $entry['reason'],
                'taxable_amount' => $entry['taxable_amount'] ?? null,
            ]))->values()->all(),
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array{id: int, name: string|null}
     */
    private function party(array $names, int $id): array
    {
        return ['id' => $id, 'name' => $names[$id] ?? null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function visible(array $row): array
    {
        foreach (['taxable_amount', 'net_payable'] as $field) {
            if (($this->fieldPermissions[$field]['visible'] ?? true) !== true) {
                unset($row[$field]);
            }
        }

        return $row;
    }
}
