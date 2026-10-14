<?php

namespace App\Tables;

use App\Enums\InvoicePaymentStatus;
use App\Http\Requests\Invoices\InvoiceDetailsRequest;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use App\Services\Invoices\InvoiceService;
use App\Services\Table\FormRequestCellValidator;
use App\Tables\Invoices\InvoiceColumnCatalog;
use App\Tables\Invoices\InvoiceDerivedQuery;
use App\Tables\Invoices\InvoiceReminderColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
    public function __construct(
        private readonly InvoiceService $service,
        private readonly FormRequestCellValidator $cells,
    ) {}

    /**
     * Spec 0260, D-3: the cell edit follows the details endpoint
     * (InvoiceDetailsRequest + InvoiceService::updateDetails), so tag and
     * deviation stay editable on a registered invoice too.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Invoice $row */
        /** @var User $actor */
        $actor = Auth::user();
        $request = $this->cells->validate(InvoiceDetailsRequest::class, 'invoice', $row, $actor, [$columnId => $value]);

        return $this->service->updateDetails($row, $request->payload(), $actor);
    }

    private const string FIRST_UNPAID_DUE_DATE = 'first_unpaid_due_date';

    /** Row actions not offered for a registered invoice. */
    private const array REGISTERED_HIDDEN_ACTIONS = ['delete'];

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
                'invoiceTag:id,name,color',
            ])
            ->withSum('installments', 'collected_amount')
            ->addSelect([InvoiceReminderColumn::ID => InvoiceReminderColumn::subquery()])
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
        $lastReminder = $row->getAttribute(InvoiceReminderColumn::ID);

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
            InvoiceReminderColumn::ID => is_string($lastReminder) ? Carbon::parse($lastReminder)->toDateString() : null,
            'tag' => $row->invoiceTag === null ? null : ['id' => $row->invoiceTag->id, 'name' => $row->invoiceTag->name, 'color' => $row->invoiceTag->color],
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

        // Action key => policy ability. No edit/details: the record edits in place from `view` (spec 0261).
        $abilities = ['view' => 'view', 'delete' => 'delete', 'activity' => 'viewActivity', 'pdf' => 'view', 'email' => 'sendEmail', 'remind' => 'sendEmail'];

        foreach ($abilities as $key => $ability) {
            // A registered invoice cannot be deleted (spec 0243, D-3).
            if ($row->isRegistered() && in_array($key, self::REGISTERED_HIDDEN_ACTIONS, true)) {
                continue;
            }

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
        return InvoiceDerivedQuery::applyFilter($query, $columnId, $filter)
            || InvoiceReminderColumn::applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return InvoiceDerivedQuery::applySort($query, $columnId, $direction)
            || InvoiceReminderColumn::applySort($query, $columnId, $direction);
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
