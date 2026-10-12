<?php

namespace App\Http\Resources;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseInvoiceInstallment for the payable installments
 * module (spec 0211). Expects RELATIONS loaded (lazy loading is forbidden).
 * Amount, payment and account fields the actor cannot see by field permission
 * are omitted, from the installment and from the document summary alike.
 *
 * @mixin PurchaseInvoiceInstallment
 */
class PurchaseInvoiceInstallmentResource extends JsonResource
{
    /** Relations the projection reads. */
    public const array RELATIONS = [
        'financialAccount:id,name',
        'purchaseInvoice',
        'purchaseInvoice.supplier:id,name',
        'purchaseInvoice.company:id,denomination',
        'purchaseInvoice.financialAccount:id,name',
        'purchaseInvoice.purchaseOrders:id,number,year',
        'purchaseInvoice.installments',
    ];

    /** Fields exposed in `field_permissions`. */
    private const array PERMISSION_FIELDS = ['due_date', 'payment_method_code', 'amount', 'paid_amount', 'paid_at', 'residual_amount', 'financial_account'];

    /** Fields dropped from the payload when not visible. */
    private const array GATED_FIELDS = ['amount', 'paid_amount', 'paid_at', 'residual_amount'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $permissions = app(AuthorizationRegistry::class)->resolve('purchase-invoice-installments')->fieldPermissions($actor, $this->resource);
        $daysOverdue = $this->daysOverdue();

        $payload = [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'due_date' => $this->due_date->toDateString(),
            'amount' => $this->amount,
            'payment_method_code' => $this->payment_method_code,
            'paid_amount' => $this->paid_amount,
            'paid_at' => $this->paid_at?->toDateString(),
            'status' => $this->status(),
            'residual_amount' => $this->residualAmount(),
            'is_overdue' => $daysOverdue > 0,
            'days_overdue' => $daysOverdue,
            'financial_account' => $this->account($this->financialAccount),
            'purchase_invoice' => $this->document($this->purchaseInvoice, $permissions),
            'field_permissions' => $this->fieldPermissions($permissions),
            'abilities' => [
                'update' => ! $this->isPaid() && $actor->can('update', $this->resource),
                'pay' => $actor->can('purchase-invoices.pay'),
            ],
        ];

        foreach (self::GATED_FIELDS as $field) {
            if (! $permissions[$field]->visible) {
                unset($payload[$field]);
            }
        }

        if (! $permissions['financial_account']->visible) {
            unset($payload['financial_account']);
        }

        return $payload;
    }

    /**
     * @param  array<string, FieldPermission>  $permissions
     * @return array<string, mixed>
     */
    private function document(PurchaseInvoice $invoice, array $permissions): array
    {
        $showAmounts = $permissions['amount']->visible;
        $showPaid = $permissions['paid_amount']->visible;

        $document = [
            'id' => $invoice->id,
            'document_type' => $invoice->document_type,
            'document_number' => $invoice->document_number,
            'document_date' => $invoice->document_date->toDateString(),
            'total_amount' => $invoice->total_amount,
            'supplier' => ['id' => $invoice->supplier->id, 'name' => $invoice->supplier->name],
            'company' => ['id' => $invoice->company->id, 'name' => $invoice->company->denomination],
            'financial_account' => $this->account($invoice->financialAccount),
            'purchase_orders' => $invoice->purchaseOrders
                ->sortBy([['year', 'asc'], ['number', 'asc']])
                ->map(fn (PurchaseOrder $order): array => ['id' => $order->id, 'code' => $order->code()])
                ->values()
                ->all(),
            'installments' => $invoice->installments->map(function (PurchaseInvoiceInstallment $row) use ($showAmounts, $showPaid): array {
                $item = [
                    'id' => $row->id,
                    'sequence' => $row->sequence,
                    'due_date' => $row->due_date->toDateString(),
                    'amount' => $row->amount,
                    'paid_amount' => $row->paid_amount,
                    'status' => $row->status(),
                ];

                $showAmounts || $item = array_diff_key($item, ['amount' => true]);
                $showPaid || $item = array_diff_key($item, ['paid_amount' => true]);

                return $item;
            })->values()->all(),
        ];

        $showAmounts || $document = array_diff_key($document, ['total_amount' => true]);
        $permissions['financial_account']->visible || $document = array_diff_key($document, ['financial_account' => true]);

        return $document;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function account(?FinancialAccount $account): ?array
    {
        return $account === null ? null : ['id' => $account->id, 'name' => $account->name];
    }

    /**
     * @param  array<string, FieldPermission>  $permissions
     * @return array<string, array{visible: bool, editable: bool, required: bool}>
     */
    private function fieldPermissions(array $permissions): array
    {
        $fields = [];

        foreach (self::PERMISSION_FIELDS as $field) {
            $fields[$field] = [
                'visible' => $permissions[$field]->visible,
                'editable' => $permissions[$field]->editable,
                'required' => $permissions[$field]->required,
            ];
        }

        return $fields;
    }
}
