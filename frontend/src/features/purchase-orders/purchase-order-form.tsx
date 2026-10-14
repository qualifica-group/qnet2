import { useTranslation } from 'react-i18next'
import { Save, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { useConfirm } from '@/components/confirm-dialog-context'
import { PurchaseOrderDocuments } from '@/features/purchase-orders/purchase-order-documents'
import { PurchaseOrderFulfillmentSection } from '@/features/purchase-orders/purchase-order-fulfillment-section'
import { PurchaseOrderFormActions } from '@/features/purchase-orders/purchase-order-form-actions'
import { PurchaseOrderHeaderFields } from '@/features/purchase-orders/purchase-order-header-fields'
import { PurchaseOrderLinesSection } from '@/features/purchase-orders/purchase-order-lines-section'
import { PurchaseOrderReceiptsSection } from '@/features/purchase-orders/purchase-order-receipts-section'
import { isOrderReadOnly } from '@/features/purchase-orders/purchase-order-permissions'
import { orderRefs } from '@/features/purchase-orders/purchase-order-refs'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import { EDITABLE_STATUSES, type PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderDelete } from '@/features/purchase-orders/use-purchase-order-actions'
import { usePurchaseOrderForm } from '@/features/purchase-orders/use-purchase-order-form'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'

const FORM_ID = 'purchase-order-form'
const SECTION_CLASS = 'flex flex-col gap-3 rounded-lg border bg-card p-3'

interface PurchaseOrderFormProps {
  /** The loaded ODA (edit); absent while creating. */
  order?: PurchaseOrder
  /** `createdIds` holds every order created at once (more than one with copies). */
  onSaved: (saved: PurchaseOrder, createdIds: number[]) => void
  onCancel: () => void
  onDeleted: () => void
}

/**
 * Create/edit page body of an ODA: header, lines, documents and totals.
 * Expects a `ResourcePermissionsProvider` above it (field matrix). An order
 * outside draft/in_validation, or one the actor cannot update, renders fully
 * read-only (D-10). Record-level actions plug into `PurchaseOrderFormActions`.
 */
export function PurchaseOrderForm({ order, onSaved, onCancel, onDeleted }: PurchaseOrderFormProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const removeOrder = usePurchaseOrderDelete()
  const { nameOf } = usePurchaseOrderStatusCatalog()
  const { form, lineArray, lines, companyId, supplierId, supplierQualified, formErrors, addLine, proposeWorkOrder, submit, isSaving } =
    usePurchaseOrderForm({ order, onSaved })
  const readOnly = order !== undefined && isOrderReadOnly(order)
  const statusLocked = order !== undefined && !EDITABLE_STATUSES.includes(order.status)
  const canDelete = order !== undefined && order.abilities.delete && !statusLocked

  const handleDelete = async () => {
    if (!order) return
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('purchaseOrderForm.deleteConfirm.title'),
      description: t('purchaseOrderForm.deleteConfirm.description', { code: order.code }),
      confirmLabel: t('purchaseOrderForm.deleteConfirm.confirm'),
    })
    if (confirmed && (await removeOrder(order.id))) {
      onDeleted()
    }
  }

  return (
    <Form {...form}>
      <form id={FORM_ID} noValidate onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <h1 className="text-xl font-semibold">
              {order ? t('purchaseOrderForm.editTitle', { code: order.code }) : t('purchaseOrderForm.createTitle')}
            </h1>
            {order ? <PurchaseOrderStatusBadge status={order.status} /> : null}
          </div>
          {order ? <PurchaseOrderFormActions order={order} isDirty={form.formState.isDirty} /> : null}
        </div>

        {statusLocked ? (
          <p role="status" className="rounded-md border bg-surface px-3 py-2 text-xs">
            {t('purchaseOrderForm.readOnlyNotice', { status: nameOf(order.status).toLowerCase() })}
          </p>
        ) : null}
        {formErrors.length > 0 ? (
          <ul role="alert" className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
            {formErrors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        ) : null}

        <section className={SECTION_CLASS} aria-label={t('purchaseOrderForm.sections.header')}>
          <h2 className="text-base font-semibold">{t('purchaseOrderForm.sections.header')}</h2>
          <PurchaseOrderHeaderFields
            control={form.control}
            setValue={form.setValue}
            refs={orderRefs(order)}
            order={order}
            companyId={companyId}
            supplierId={supplierId}
            supplierQualified={supplierQualified}
            onWorkOrderChange={proposeWorkOrder}
          />
        </section>

        <PurchaseOrderLinesSection
          control={form.control}
          setValue={form.setValue}
          fields={lineArray.fields}
          lines={lines}
          onAdd={addLine}
          onRemove={lineArray.remove}
          readOnly={readOnly}
          error={form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message}
        />

        {order && statusLocked ? (
          <>
            <PurchaseOrderFulfillmentSection order={order} />
            <PurchaseOrderReceiptsSection order={order} />
          </>
        ) : null}

        <section className={SECTION_CLASS} aria-label={t('purchaseOrderForm.sections.documents')}>
          <h2 className="text-base font-semibold">{t('purchaseOrderForm.sections.documents')}</h2>
          <PurchaseOrderDocuments control={form.control} orderId={order?.id} />
        </section>

        <div className="flex flex-wrap items-center justify-end gap-2">
          {canDelete ? (
            <Button type="button" variant="outline" size="sm" className="mr-auto bg-card text-destructive" onClick={() => void handleDelete()}>
              <Trash2 aria-hidden="true" />
              {t('purchaseOrderForm.delete')}
            </Button>
          ) : null}
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCancel}>
            {t('purchaseOrderForm.closeForm')}
          </Button>
          {readOnly ? null : (
            <Button type="submit" size="sm" disabled={isSaving}>
              <Save aria-hidden="true" />
              {t(order ? 'purchaseOrderForm.update' : 'purchaseOrderForm.save')}
            </Button>
          )}
        </div>
      </form>
    </Form>
  )
}
