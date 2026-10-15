<?php

namespace App\Tables;

use App\Enums\InvoicePaymentStatus;
use App\Enums\PurchaseInvoiceType;
use App\Http\Requests\PurchaseInvoices\PurchaseInvoiceWriteRequest;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\User;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use App\Services\PurchaseInvoices\PurchaseInvoiceService;
use App\Services\Table\FormRequestCellValidator;
use App\Tables\PurchaseInvoiceInstallments\PurchaseInstallmentSql;
use App\Tables\PurchaseInvoices\PurchaseInvoiceColumnCatalog;
use App\Tables\PurchaseInvoices\PurchaseInvoiceDerivedQuery;
use App\Tables\PurchaseInvoices\PurchaseInvoicePayableColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `purchase-invoices` domain (spec 0250, D-10): one
 * row per payable document. The paid amount comes from a withSum, the linked
 * orders from one grouped scalar and the payment status from a correlated
 * subquery on the first unpaid installment, so the grid never runs per-row
 * queries. Mirror of the active `invoices` domain (spec 0194).
 */
class PurchaseInvoicesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly PurchaseInvoiceService $service,
        private readonly FormRequestCellValidator $cells,
    ) {}

    /**
     * Spec 0262, D-3: only a proforma without payments takes a cell edit (a
     * registered invoice is frozen, payments freeze the document date).
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        /** @var PurchaseInvoice $row */
        return $row->document_type === PurchaseInvoiceType::Proforma
            && (float) ($row->getAttribute(self::PAID_SUM) ?? 0) <= 0
            && Gate::forUser($actor)->allows('update', $row);
    }

    /** Spec 0262, D-3: the cell edit is the partial PATCH (stored values merged) through the form and the service. */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var PurchaseInvoice $row */
        /** @var User $actor */
        $actor = Auth::user();
        $request = $this->cells->validate(PurchaseInvoiceWriteRequest::class, 'purchaseInvoice', $row, $actor, [$columnId => $value]);

        return $this->service->update($row, $request->payload());
    }

    private const string FIRST_UNPAID_DUE_DATE = 'first_unpaid_due_date';

    private const string PAID_SUM = 'installments_sum_paid_amount';

    private const string ORDERS_LABEL = 'purchase_orders_label';

    /** Row actions not offered for a registered invoice (409 on the server). */
    private const array INVOICE_HIDDEN_ACTIONS = ['delete'];

    public function domain(): string
    {
        return 'purchase-invoices';
    }

    /**
     * @return class-string<PurchaseInvoice>
     */
    public function modelClass(): string
    {
        return PurchaseInvoice::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives PurchaseInvoicePolicy::viewAny from modelClass().

    /**
     * @return Builder<PurchaseInvoice>
     */
    public function baseQuery(): Builder
    {
        return PurchaseInvoice::query()
            ->select('purchase_invoices.*')
            ->selectRaw(PurchaseInstallmentSql::purchaseOrderCodes('purchase_invoices.id').' as '.self::ORDERS_LABEL)
            ->with(['supplier:id,name', 'company:id,denomination'])
            ->withSum('installments', 'paid_amount')
            ->withCount('installments')
            ->withExists('commissionSettlement')
            ->addSelect([self::FIRST_UNPAID_DUE_DATE => PurchaseInvoiceInstallment::query()
                ->select('due_date')
                ->whereColumn('purchase_invoice_installments.purchase_invoice_id', 'purchase_invoices.id')
                ->where(static function (Builder $unpaid): void {
                    $unpaid->whereNull('paid_amount')->orWhere('paid_amount', '<=', 0);
                })
                ->orderBy('sequence')
                ->limit(1)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PurchaseInvoiceColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PurchaseInvoiceColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PurchaseInvoiceColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'document_date', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
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
     * Frontend `enums.*` key of the document type badge.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'document_type' ? 'purchase_invoice_type' : null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'document_type'
            ? array_map(static fn ($meta): array => $meta->toArray(), PurchaseInvoiceType::options())
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PurchaseInvoice $row */
        $paid = (float) ($row->getAttribute(self::PAID_SUM) ?? 0);

        return [
            'id' => $row->id,
            'document_type' => $row->document_type->value,
            'document_number' => $row->document_number,
            'document_date' => $row->document_date->toDateString(),
            'supplier_id' => $row->supplier_id,
            'supplier' => $row->supplier?->name,
            'company' => $row->company?->denomination,
            'purchase_orders' => $row->getAttribute(self::ORDERS_LABEL),
            'net_amount' => $row->net_amount,
            'vat_amount' => $row->vat_amount,
            'total_amount' => $row->total_amount,
            'payable_amount' => $row->payableAmount(),
            'paid_amount' => self::money($paid),
            'residual_amount' => self::money((float) $row->payableAmount() - $paid),
            'payment_status' => $this->paymentStatus($row)->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var PurchaseInvoice $row */
        $gate = Gate::forUser($actor);
        $hasPayments = (float) ($row->getAttribute(self::PAID_SUM) ?? 0) > 0;
        $allowed = [];

        // Action key => policy ability. No edit/details: the record edits in place from `view` (spec 0262).
        $abilities = ['view' => 'view', 'delete' => 'delete', 'activity' => 'viewActivity'];

        foreach ($abilities as $key => $ability) {
            // A registered invoice cannot be deleted, nor a document with payments.
            if ($row->document_type === PurchaseInvoiceType::Invoice && in_array($key, self::INVOICE_HIDDEN_ACTIONS, true)) {
                continue;
            }

            // A document born from a commission settlement is owned by it (spec 0267, D-5).
            if ($key === 'delete' && ($hasPayments || $row->getAttribute('commission_settlement_exists'))) {
                continue;
            }

            if ($gate->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return PurchaseInvoiceDerivedQuery::applyFilter($query, $columnId, $filter)
            || PurchaseInvoicePayableColumn::applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return PurchaseInvoiceDerivedQuery::applySort($query, $columnId, $direction)
            || PurchaseInvoicePayableColumn::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<PurchaseInvoice>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return PurchaseInvoiceDerivedQuery::distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * Paid when no installment is left unpaid, otherwise the state of the first
     * unpaid one; a document with no schedule has nothing due.
     */
    private function paymentStatus(PurchaseInvoice $row): InvoicePaymentStatus
    {
        $dueDate = $row->getAttribute(self::FIRST_UNPAID_DUE_DATE);

        if (is_string($dueDate)) {
            return app(InvoicePaymentStatusResolver::class)->forDueDate(Carbon::parse($dueDate), Carbon::today());
        }

        return (int) $row->getAttribute('installments_count') === 0 ? InvoicePaymentStatus::NotDue : InvoicePaymentStatus::Paid;
    }

    private static function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
