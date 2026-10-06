<?php

namespace App\Tables;

use App\Enums\InvoicePaymentStatus;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use App\Tables\Invoices\InvoiceColumnCatalog;
use App\Tables\Invoices\InvoiceDerivedQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `invoices` domain (spec 0194). The collected amount
 * comes from a withSum and the payment status from a correlated subquery on
 * the first unpaid installment, so the grid never runs per-row queries.
 * Customer/company/payment method and the year/month strip are derived
 * filters (see InvoiceDerivedQuery).
 */
class InvoicesTableDefinition extends AbstractTableDefinition
{
    private const string FIRST_UNPAID_DUE_DATE = 'first_unpaid_due_date';

    private const string COLLECTED_SUM = 'installments_sum_collected_amount';

    public function domain(): string
    {
        return 'invoices';
    }

    /**
     * @return class-string<Invoice>
     */
    public function modelClass(): string
    {
        return Invoice::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives InvoicePolicy::viewAny from modelClass().

    /**
     * @return Builder<Invoice>
     */
    public function baseQuery(): Builder
    {
        return Invoice::query()
            ->with([
                'company:id,denomination',
                'customer:id,name',
                'paymentMethod:id,name',
                'workOrder:id,code',
                'quote:id,code',
            ])
            ->withSum('installments', 'collected_amount')
            ->addSelect([self::FIRST_UNPAID_DUE_DATE => InvoiceInstallment::query()
                ->select('due_date')
                ->whereColumn('invoice_installments.invoice_id', 'invoices.id')
                ->where(static function (Builder $unpaid): void {
                    $unpaid->whereNull('collected_amount')->orWhereColumn('collected_amount', '<', 'amount');
                })
                ->orderBy('sequence')
                ->limit(1)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return InvoiceColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return InvoiceColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return InvoiceColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'document_date', 'direction' => 'desc'],
            ['columnId' => 'number', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Invoice $row */
        $collected = (float) ($row->getAttribute(self::COLLECTED_SUM) ?? 0);
        $dueDate = $row->getAttribute(self::FIRST_UNPAID_DUE_DATE);

        return [
            'id' => $row->id,
            'number_label' => $row->number.'/'.$row->year,
            'number' => $row->number,
            'document_date' => $row->document_date->toDateString(),
            'type' => $row->type->value,
            'external_number' => $row->external_number,
            'external_date' => $row->external_date?->toDateString(),
            'customer' => $row->customer?->name,
            'company' => $row->company?->denomination,
            'payment_method' => $row->paymentMethod?->name,
            'work_order_code' => $row->workOrder?->code,
            'quote_code' => $row->quote?->code,
            'net_amount' => $row->net_amount,
            'vat_amount' => $row->vat_amount,
            'total_amount' => $row->total_amount,
            'collected_amount' => self::money($collected),
            'residual_amount' => self::money((float) $row->total_amount - $collected),
            'payment_status' => (is_string($dueDate)
                ? app(InvoicePaymentStatusResolver::class)->forDueDate(Carbon::parse($dueDate), Carbon::today())
                : InvoicePaymentStatus::Paid)->value,
            'tag' => $row->tag?->value,
            'deviation' => $row->deviation,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Invoice $row */
        $gate = Gate::forUser($actor);
        $allowed = [];

        // Action key => policy ability; "details" is the PATCH, gated like update.
        $abilities = ['view' => 'view', 'update' => 'update', 'details' => 'update', 'delete' => 'delete', 'activity' => 'viewActivity'];

        foreach ($abilities as $key => $ability) {
            if ($gate->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Footer totals over the WHOLE filtered set (spec 0156, D-3), as 2-decimal
     * strings; residual is total minus collected.
     *
     * @param  Builder<Invoice>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        $ids = (clone $query)->reorder()->select('invoices.id');

        $total = (float) Invoice::query()->whereIn('invoices.id', $ids)->sum('total_amount');
        $collected = (float) InvoiceInstallment::query()->whereIn('invoice_id', $ids)->sum('collected_amount');

        return [
            'net_amount' => self::money((float) Invoice::query()->whereIn('invoices.id', $ids)->sum('net_amount')),
            'vat_amount' => self::money((float) Invoice::query()->whereIn('invoices.id', $ids)->sum('vat_amount')),
            'total_amount' => self::money($total),
            'collected_amount' => self::money($collected),
            'residual_amount' => self::money($total - $collected),
        ];
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return InvoiceDerivedQuery::applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return InvoiceDerivedQuery::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return InvoiceDerivedQuery::distinctValues($columnId, $search, $query, $limit);
    }

    private static function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
