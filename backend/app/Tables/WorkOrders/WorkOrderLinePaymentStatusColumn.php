<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\ProductTypology;
use App\Models\WorkOrder;
use App\Models\WorkOrderPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The "Stato pagamento" column of the commesse grids (user directives
 * 2026-11-17): the payment statuses of the commessa's products grouped by
 * product typology (Consulenza, Ente...), a product without status counted as
 * "Nessuno stato". Each group carries its products for the cell tooltip and a
 * `name` ("Consulenza: Saldato, Nessuno stato") the export joins.
 *
 * Filter values are typology + status pairs keyed by id (`3-7`, `3-none`), so
 * a saved filter survives renames and language changes; their labels
 * ("Consulenza: Saldato") travel in the column `badges`. A commessa matches
 * when ANY of its products has a picked pair. A to-many: not sortable.
 */
final class WorkOrderLinePaymentStatusColumn
{
    public const string COLUMN = 'line_payment_statuses';

    /** @var array<int, string> */
    public const array EAGER_LOADS = ['linePayments.status', 'quoteLines.product.productTypology'];

    private const string NO_STATUS = 'none';

    private const string KEY_PATTERN = '/^(\d+)-(\d+|none)$/';

    private const string NO_STATUS_COLOR = 'gray';

    /**
     * @return array<int, array{name: string, typology: array{name: string, color: string}|null, statuses: array<int, array{name: string, color: string}|null>, products: array<int, array{code: string|null, name: string|null, status: string|null}>}>
     */
    public function project(WorkOrder $workOrder): array
    {
        $payments = $workOrder->linePayments->keyBy('quote_line_id');
        $groups = [];

        foreach ($workOrder->quoteLines as $line) {
            $product = $line->product;
            $typology = $product?->productTypology;
            $status = $payments->get($line->id)?->status;
            $key = $typology?->id ?? 0;
            $groups[$key] ??= ['typology' => $typology === null ? null : ['name' => $typology->name, 'color' => $typology->color], 'statuses' => [], 'products' => []];
            $groups[$key]['statuses'][$status?->id ?? self::NO_STATUS] = $status === null ? null : ['name' => $status->name, 'color' => $status->color];
            $groups[$key]['products'][] = ['code' => $product?->code, 'name' => $product?->name, 'status' => $status?->name];
        }

        return array_values(array_map(fn (array $group): array => [
            'name' => $this->groupName($group['typology']['name'] ?? null, $group['statuses']),
            'typology' => $group['typology'],
            'statuses' => array_values($group['statuses']),
            'products' => $group['products'],
        ], $groups));
    }

    /**
     * Every typology + status pair, typologies by name, statuses in their
     * configured order and "Nessuno stato" last: the labels of the filter values.
     *
     * @return array<int, array{value: string, label: string, color: string, icon: null}>
     */
    public function badges(): array
    {
        $statuses = WorkOrderPaymentStatus::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'color']);
        $badges = [];

        foreach (ProductTypology::query()->orderBy('name')->orderBy('id')->get(['id', 'name']) as $typology) {
            foreach ($statuses as $status) {
                $badges[] = $this->badge("{$typology->id}-{$status->id}", $typology->name, $status->name, (string) $status->color);
            }
            $badges[] = $this->badge("{$typology->id}-".self::NO_STATUS, $typology->name, $this->noStatusLabel(), self::NO_STATUS_COLOR);
        }

        return $badges;
    }

    /**
     * Bound ids parsed from the allow-listed key shape, never a raw fragment (backend.md §8).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $pairs = $this->parsePairs(is_array($filter['values'] ?? null) ? $filter['values'] : []);

        if ($pairs === []) {
            return;
        }

        $query->where(function (Builder $group) use ($pairs): void {
            foreach ($pairs as [$typologyId, $statusId]) {
                $group->orWhereHas('quoteLines', function (Builder $lines) use ($typologyId, $statusId): void {
                    $lines->whereHas('product', static fn (Builder $product) => $product->where('product_typology_id', $typologyId));
                    $statusId === null
                        ? $lines->whereNotExists(fn (QueryBuilder $payment) => $this->paymentOfLine($payment)->whereNotNull('work_order_payment_status_id'))
                        : $lines->whereExists(fn (QueryBuilder $payment) => $this->paymentOfLine($payment)->where('work_order_payment_status_id', $statusId));
                });
            }
        });
    }

    /**
     * The pairs found on the products of the commesse matching $query (every
     * OTHER active filter applies), in the order of badges().
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $used = DB::table('quote_line_work_order')
            ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->leftJoin('work_order_line_payments', 'work_order_line_payments.quote_line_id', '=', 'quote_lines.id')
            ->whereIn('quote_line_work_order.work_order_id', (clone $query)->select('work_orders.id'))
            ->distinct()
            ->get(['products.product_typology_id', 'work_order_line_payments.work_order_payment_status_id'])
            ->map(static fn (object $pair): string => $pair->product_typology_id.'-'.($pair->work_order_payment_status_id ?? self::NO_STATUS))
            ->flip();
        $needle = $search === null ? '' : mb_strtolower($search);

        return collect($this->badges())
            ->filter(static fn (array $badge): bool => $used->has($badge['value'])
                && ($needle === '' || str_contains(mb_strtolower($badge['label']), $needle)))
            ->take($limit)
            ->pluck('value')
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, array{name: string, color: string}|null>  $statuses
     */
    private function groupName(?string $typologyName, array $statuses): string
    {
        $names = implode(', ', array_map(fn (?array $status): string => $status['name'] ?? $this->noStatusLabel(), $statuses));

        return $typologyName === null ? $names : "{$typologyName}: {$names}";
    }

    /**
     * @return array{value: string, label: string, color: string, icon: null}
     */
    private function badge(string $value, string $typologyName, string $statusName, string $color): array
    {
        return ['value' => $value, 'label' => "{$typologyName}: {$statusName}", 'color' => $color, 'icon' => null];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int, array{0: int, 1: int|null}>
     */
    private function parsePairs(array $values): array
    {
        $pairs = [];

        foreach ($values as $value) {
            if (is_string($value) && preg_match(self::KEY_PATTERN, $value, $match) === 1) {
                $pairs[] = [(int) $match[1], $match[2] === self::NO_STATUS ? null : (int) $match[2]];
            }
        }

        return $pairs;
    }

    private function paymentOfLine(QueryBuilder $payment): QueryBuilder
    {
        return $payment->from('work_order_line_payments')->whereColumn('work_order_line_payments.quote_line_id', 'quote_lines.id');
    }

    private function noStatusLabel(): string
    {
        return __('work_orders.line_payment_statuses.no_status');
    }
}
