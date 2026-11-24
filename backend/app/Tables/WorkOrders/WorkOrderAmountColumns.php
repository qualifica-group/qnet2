<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\CommissionRecipientRole;
use App\Enums\SupplierCommissionDirection;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Table\FilterApplier;
use App\Services\WorkOrders\LineEffectiveRevenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The amount columns of the commesse grids (legacy `OrdersTable`
 * `importo_consulenza` / `totale_ricavi`, user request 2026-11-24), over the
 * commessa's own revenue lines:
 * - `consultancy_amount`: net amount of the lines whose product typology is
 *   Consulenza (legacy: price * quantity of the `folder = 1` services);
 * - `total_revenue`: effective revenue of every line, the same figure as the
 *   "Dati contrattuali" total (LineEffectiveRevenue: a line with a RECEIVED
 *   supplier commission earns only that commission, any other its net).
 *
 * Shown, filtered and sorted only for whoever sees the contract data (like the
 * legacy "commessa importi"). The cell is computed from the eager-loaded lines;
 * filter and sort run on correlated subqueries with the same rule.
 */
final class WorkOrderAmountColumns
{
    public const string CONSULTANCY_AMOUNT = 'consultancy_amount';

    public const string TOTAL_REVENUE = 'total_revenue';

    /** @var array<int, string> */
    public const array EAGER_LOADS = ['quoteLines.product.productTypology', 'quoteLines.commissions'];

    /** WorkOrderPolicy::viewContractData (spec 0380: together with viewCosts). */
    private const array PERMISSIONS = ['work-orders.viewContractData', 'work-orders.viewCosts'];

    private const string CONSULTANCY_TYPOLOGY = 'consultancy';

    private const string DERIVED_TABLE = 'work_order_amounts';

    public function __construct(
        private readonly FilterApplier $filterApplier,
        private readonly LineEffectiveRevenue $effectiveRevenue,
    ) {}

    public static function isVisibleTo(?User $actor): bool
    {
        foreach (self::PERMISSIONS as $permission) {
            if ($actor?->can($permission) !== true) {
                return false;
            }
        }

        return true;
    }

    public static function handles(string $columnId): bool
    {
        return in_array($columnId, [self::CONSULTANCY_AMOUNT, self::TOTAL_REVENUE], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return array_map(static fn (string $id): array => [
            'id' => $id,
            'label' => "workOrders.columns.{$id}",
            'type' => 'number',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'number',
            'hasFilterValues' => false,
        ], [self::CONSULTANCY_AMOUNT, self::TOTAL_REVENUE]);
    }

    /**
     * @return array<int, array{columnId: string, type: string}>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => self::CONSULTANCY_AMOUNT, 'type' => 'number'],
            ['columnId' => self::TOTAL_REVENUE, 'type' => 'number'],
        ];
    }

    /**
     * @return array{consultancy_amount: string, total_revenue: string}
     */
    public function project(WorkOrder $workOrder): array
    {
        $consultancy = 0;
        $revenue = 0;

        foreach ($workOrder->quoteLines as $line) {
            $revenue += $this->effectiveRevenue->cents($line);

            if ($line->product?->productTypology?->code === self::CONSULTANCY_TYPOLOGY) {
                $consultancy += $this->toCents($line->net_amount);
            }
        }

        return [
            self::CONSULTANCY_AMOUNT => $this->format($consultancy),
            self::TOTAL_REVENUE => $this->format($revenue),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): void
    {
        $amounts = WorkOrder::query()
            ->fromSub(
                WorkOrder::query()->select('work_orders.id')->selectSub($this->amountSubquery($columnId), $columnId)->toBase(),
                self::DERIVED_TABLE,
            )
            ->select(self::DERIVED_TABLE.'.id');

        $this->filterApplier->apply($amounts, self::DERIVED_TABLE.'.'.$columnId, $columnConfig, $filter);

        $query->whereIn('work_orders.id', $amounts);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): void
    {
        $query->orderBy($this->amountSubquery($columnId), $direction);
    }

    private function amountSubquery(string $columnId): QueryBuilder
    {
        $lines = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->whereColumn('quote_line_work_order.work_order_id', 'work_orders.id');

        if ($columnId === self::CONSULTANCY_AMOUNT) {
            return $lines
                ->join('products', 'products.id', '=', 'quote_lines.product_id')
                ->join('product_typologies', 'product_typologies.id', '=', 'products.product_typology_id')
                ->where('product_typologies.code', self::CONSULTANCY_TYPOLOGY)
                ->selectRaw('COALESCE(SUM(quote_lines.net_amount), 0)');
        }

        // The LineEffectiveRevenue rule: a RECEIVED supplier commission line earns only its Fornitore commissions.
        return $lines->selectRaw(
            'COALESCE(SUM(CASE WHEN quote_lines.supplier_commission_direction = ? THEN COALESCE((
                SELECT SUM(quote_line_commissions.calculated_amount) FROM quote_line_commissions
                WHERE quote_line_commissions.quote_line_id = quote_lines.id AND quote_line_commissions.recipient_role = ?
            ), 0) ELSE quote_lines.net_amount END), 0)',
            [SupplierCommissionDirection::Received->value, CommissionRecipientRole::Supplier->value],
        );
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
