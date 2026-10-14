import { useTranslation } from 'react-i18next'
import { ArrowLeft, Paperclip, ShoppingBag, Trash2 } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordSection,
  RecordSectionsGrid,
  RecordStat,
  RecordStatStrip,
} from '@/components/detail/record-panel'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { PurchaseOrderDocuments } from '@/features/purchase-orders/purchase-order-documents'
import { PurchaseOrderFormActions } from '@/features/purchase-orders/purchase-order-form-actions'
import { PurchaseOrderFulfillmentSection } from '@/features/purchase-orders/purchase-order-fulfillment-section'
import { PurchaseOrderInvoicesSection } from '@/features/purchase-orders/purchase-order-invoices-section'
import { PurchaseOrderLinesRecord } from '@/features/purchase-orders/purchase-order-lines-record'
import { toOrderResourcePermissions } from '@/features/purchase-orders/purchase-order-permissions'
import { PurchaseOrderReceiptsSection } from '@/features/purchase-orders/purchase-order-receipts-section'
import { PurchaseOrderRecordSections } from '@/features/purchase-orders/purchase-order-record-sections'
import { PurchaseOrderStatusBadge } from '@/features/purchase-orders/purchase-order-status-badge'
import { EDITABLE_STATUSES, type PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderDelete } from '@/features/purchase-orders/use-purchase-order-actions'
import { usePurchaseOrderInlineEdit } from '@/features/purchase-orders/use-purchase-order-inline-edit'

interface PurchaseOrderDetailViewProps {
  order: PurchaseOrder
  onBack: () => void
  onDeleted: () => void
  /** After an in-place save, so the host refreshes what lists the ODA. */
  onChanged?: () => void
}

/**
 * The ODA record (spec 0257): identity band with the record actions, the
 * totals strip, then order, organization, tags and notes, lines and
 * documents. There is no Save button: every field and every line edits in
 * place, one at a time, and only while the order is a draft or in validation.
 * Fulfilment, receipts and invoices follow as their own cards afterwards.
 */
export function PurchaseOrderDetailView(props: PurchaseOrderDetailViewProps) {
  // The inline editors read the field permissions, so the provider wraps the whole record.
  return (
    <ResourcePermissionsProvider permissions={toOrderResourcePermissions(props.order)}>
      <PurchaseOrderDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PurchaseOrderDetailContent({ order, onBack, onDeleted, onChanged }: PurchaseOrderDetailViewProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const removeOrder = usePurchaseOrderDelete()
  const { nameOf } = usePurchaseOrderStatusCatalog()
  const editor = usePurchaseOrderInlineEdit(order, onChanged)
  const statusLocked = !EDITABLE_STATUSES.includes(order.status)
  const canDelete = order.abilities.delete && !statusLocked

  const handleDelete = async () => {
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
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={order.description} icon={<ShoppingBag />} className="size-10 text-base [&>svg]:size-5" />}
          title={t('purchaseOrderForm.editTitle', { code: order.code })}
          subtitle={order.description}
          badges={<PurchaseOrderStatusBadge status={order.status} />}
          actions={
            <Button type="button" variant="secondary" size="sm" onClick={onBack}>
              <ArrowLeft aria-hidden="true" />
              {t('common.back')}
            </Button>
          }
        />
        <div className="flex flex-wrap items-center gap-2 border-b px-4 py-2">
          <PurchaseOrderFormActions order={order} isDirty={editor.inline.editingField !== null}>
            {canDelete ? (
              <Button type="button" variant="outline" size="xs" className="text-destructive" onClick={() => void handleDelete()}>
                <Trash2 aria-hidden="true" />
                {t('purchaseOrderForm.delete')}
              </Button>
            ) : null}
          </PurchaseOrderFormActions>
        </div>
        <RecordStatStrip>
          <RecordStat label={t('purchaseOrderForm.totals.taxable')} value={formatQuoteAmount(Number(order.taxable_total))} />
          <RecordStat label={t('purchaseOrderForm.totals.vat')} value={formatQuoteAmount(Number(order.vat_total))} />
          <RecordStat label={t('purchaseOrderForm.totals.grandTotal')} value={formatQuoteAmount(Number(order.grand_total))} />
          <RecordStat label={t('purchaseOrderForm.sections.lines')} value={order.lines.length} />
        </RecordStatStrip>

        {statusLocked ? (
          <p role="status" className="mx-4 mt-3 rounded-md border bg-surface px-3 py-2 text-xs">
            {t(order.abilities.update ? 'purchaseOrderForm.serviceOnlyNotice' : 'purchaseOrderForm.readOnlyNotice', {
              status: nameOf(order.status).toLowerCase(),
            })}
          </p>
        ) : null}

        {/* Provider only (no DOM): the inline editors share the ODA form. */}
        <Form {...editor.form}>
          <RecordSectionsGrid>
            <PurchaseOrderRecordSections order={order} editor={editor} />
            <PurchaseOrderLinesRecord order={order} editor={editor} />
            <RecordSection title={t('purchaseOrderForm.sections.documents')} icon={<Paperclip />} full>
              <PurchaseOrderDocuments control={editor.form.control} orderId={order.id} />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordCard>

      {statusLocked ? (
        <>
          <PurchaseOrderFulfillmentSection order={order} />
          <PurchaseOrderReceiptsSection order={order} />
          <PurchaseOrderInvoicesSection order={order} />
        </>
      ) : null}
    </RecordCanvas>
  )
}
