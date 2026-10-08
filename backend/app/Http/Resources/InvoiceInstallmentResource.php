<?php

namespace App\Http\Resources;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of an InvoiceInstallment for the installments module (spec 0197).
 * Expects RELATIONS loaded (lazy loading is forbidden). Amount and collection
 * fields the actor cannot see by field permission are omitted.
 *
 * @mixin InvoiceInstallment
 */
class InvoiceInstallmentResource extends JsonResource
{
    /** Relations the projection reads. */
    public const array RELATIONS = [
        'invoice',
        'invoice.customer:id,name',
        'invoice.workOrder:id,code,title',
        'invoice.quote:id,company_site_id,operational_site_id',
        'invoice.quote.companySite:id,name',
        'invoice.quote.operationalSite:id,alias',
    ];

    /** Fields exposed in `field_permissions`. */
    private const array PERMISSION_FIELDS = ['due_date', 'payment_method_code', 'amount', 'collected_amount', 'collected_at'];

    /** Fields dropped from the payload when not visible. */
    private const array GATED_FIELDS = ['amount', 'collected_amount', 'collected_at', 'residual_amount'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $actor */
        $actor = $request->user();
        $permissions = app(AuthorizationRegistry::class)->resolve('invoice-installments')->fieldPermissions($actor, $this->resource);
        $daysOverdue = $this->daysOverdue();
        $invoice = $this->invoice;
        $quote = $invoice->quote;

        $payload = [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'due_date' => $this->due_date->toDateString(),
            'amount' => $this->amount,
            'payment_method_code' => $this->payment_method_code,
            'collected_amount' => $this->collected_amount,
            'collected_at' => $this->collected_at?->toDateString(),
            'status' => $this->status(),
            'residual_amount' => $this->residualAmount(),
            'is_overdue' => $daysOverdue > 0,
            'days_overdue' => $daysOverdue,
            'invoice' => [
                'id' => $invoice->id,
                'number_label' => "{$invoice->number}/{$invoice->year}",
                'document_date' => $invoice->document_date->toDateString(),
                'total_amount' => $invoice->total_amount,
                'customer' => ['id' => $invoice->customer->id, 'name' => $invoice->customer->name],
                'work_order' => $invoice->workOrder === null ? null : [
                    'id' => $invoice->workOrder->id,
                    'code' => $invoice->workOrder->code,
                    'title' => $invoice->workOrder->title,
                ],
                'company_site' => $quote?->companySite === null ? null : [
                    'id' => $quote->companySite->id,
                    'name' => $quote->companySite->name,
                ],
                'operational_site' => $quote?->operationalSite === null ? null : [
                    'id' => $quote->operationalSite->id,
                    'alias' => $quote->operationalSite->alias,
                ],
            ],
            'field_permissions' => $this->fieldPermissions($permissions),
            'abilities' => [
                'update' => $this->status() === InstallmentStatus::Unpaid && $actor->can('update', $this->resource),
                'collect' => $actor->can('invoices.collect'),
                'view_invoice' => $actor->can('invoices.view'),
            ],
        ];

        foreach (self::GATED_FIELDS as $field) {
            if (! $permissions[$field]->visible) {
                unset($payload[$field]);
            }
        }

        return $payload;
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
