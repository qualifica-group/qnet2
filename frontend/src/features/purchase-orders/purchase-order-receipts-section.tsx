import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDate } from '@/lib/formatting/date-display'
import { PurchaseInvoiceLink } from '@/features/purchase-orders/purchase-order-invoices-section'
import { formatQuantity } from '@/features/purchase-orders/purchase-order-receipt-schema'
import type { PurchaseOrder, PurchaseOrderReceipt } from '@/features/purchase-orders/types'
import { useDeleteReceipt, usePurchaseOrderReceipts } from '@/features/purchase-orders/use-purchase-order-fulfillment'

interface ReceiptItemProps {
  receipt: PurchaseOrderReceipt
  canDelete: boolean
  isDeleting: boolean
  onDelete: (receipt: PurchaseOrderReceipt) => void
}

function ReceiptItem({ receipt, canDelete, isDeleting, onDelete }: ReceiptItemProps) {
  const { t } = useTranslation()
  const date = formatDate(receipt.receipt_date)
  const deliveryNote = [receipt.delivery_note_number, receipt.delivery_note_date ? formatDate(receipt.delivery_note_date) : null]
    .filter((part): part is string => part !== null && part !== '')
    .join(' - ')

  return (
    <li className="flex flex-col gap-1 rounded-md border bg-background p-2 text-xs" aria-label={t('purchaseOrderActions.receipts.itemLabel', { date })}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5">
          <span className="font-medium">{date}</span>
          {deliveryNote ? (
            <span className="text-muted-foreground">{t('purchaseOrderActions.receipts.deliveryNote', { value: deliveryNote })}</span>
          ) : null}
          {receipt.purchase_invoice ? (
            <span className="text-muted-foreground">
              <PurchaseInvoiceLink invoiceId={receipt.purchase_invoice.id}>
                {receipt.purchase_invoice.document_number
                  ? t('purchaseOrderActions.receipts.fromInvoiceNumber', { number: receipt.purchase_invoice.document_number })
                  : t('purchaseOrderActions.receipts.fromInvoice')}
              </PurchaseInvoiceLink>
            </span>
          ) : null}
          <span className="text-muted-foreground">{t('purchaseOrderActions.receipts.by', { name: receipt.created_by.name })}</span>
        </div>
        {canDelete ? (
          <Button
            type="button"
            variant="outline"
            size="xs"
            className="bg-card text-destructive"
            disabled={isDeleting}
            aria-label={t('purchaseOrderActions.receipts.deleteAction', { date })}
            onClick={() => onDelete(receipt)}
          >
            <Trash2 aria-hidden="true" />
            {t('purchaseOrderActions.receipts.delete')}
          </Button>
        ) : null}
      </div>
      <ul className="flex flex-col gap-0.5">
        {receipt.lines.map((line) => (
          <li key={line.id} className="flex justify-between gap-2">
            <span className="truncate">
              {line.position}. {line.description}
            </span>
            <span className="tabular-nums">{formatQuantity(line.quantity)}</span>
          </li>
        ))}
      </ul>
      {receipt.notes ? <p className="text-muted-foreground">{receipt.notes}</p> : null}
    </li>
  )
}

/**
 * Receipts of the ODA, most recent first (spec 0254 D-1), with the cancel
 * action under both `order.abilities.receive` and the receipt's own
 * `abilities.delete`. Cancelling asks for confirmation.
 */
export function PurchaseOrderReceiptsSection({ order }: { order: PurchaseOrder }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const receipts = usePurchaseOrderReceipts(order.id)
  const remove = useDeleteReceipt(order.id)

  const handleDelete = async (receipt: PurchaseOrderReceipt) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('purchaseOrderActions.receipts.deleteConfirm.title'),
      description: t('purchaseOrderActions.receipts.deleteConfirm.description', { date: formatDate(receipt.receipt_date) }),
      confirmLabel: t('purchaseOrderActions.receipts.deleteConfirm.confirm'),
    })
    if (confirmed) {
      remove.mutate(receipt.id)
    }
  }

  const list = receipts.data ?? []

  return (
    <section className="flex flex-col gap-3 rounded-lg border bg-card p-3" aria-label={t('purchaseOrderActions.receipts.section')}>
      <h2 className="text-base font-semibold">{t('purchaseOrderActions.receipts.section')}</h2>
      {receipts.isLoading ? (
        <Skeleton className="h-12 w-full" aria-hidden="true" />
      ) : receipts.isError ? (
        <p role="alert" className="text-xs text-destructive">
          {t('purchaseOrderActions.receipts.loadError')}
        </p>
      ) : list.length === 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('purchaseOrderActions.receipts.empty')}
        </p>
      ) : (
        <ul className="flex flex-col gap-1.5">
          {list.map((receipt) => (
            <ReceiptItem
              key={receipt.id}
              receipt={receipt}
              canDelete={order.abilities.receive && receipt.abilities.delete}
              isDeleting={remove.isPending}
              onDelete={(target) => void handleDelete(target)}
            />
          ))}
        </ul>
      )}
    </section>
  )
}
