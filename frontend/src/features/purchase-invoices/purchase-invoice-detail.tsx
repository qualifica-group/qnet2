import { useTranslation } from 'react-i18next'
import { FileText, Trash2 } from 'lucide-react'
import { DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { cn } from '@/lib/utils'
import { PURCHASE_INVOICE_TYPES } from '@/features/purchase-invoice-installments/types'
import { PurchaseInvoiceDetailInstallments } from '@/features/purchase-invoices/purchase-invoice-detail-installments'
import { PurchaseInvoiceDetailOrders } from '@/features/purchase-invoices/purchase-invoice-detail-orders'
import { PurchaseInvoiceDocuments } from '@/features/purchase-invoices/purchase-invoice-documents'
import { PurchaseInvoiceLinesRecord } from '@/features/purchase-invoices/purchase-invoice-lines-record'
import { PurchaseInvoiceRecordTotals } from '@/features/purchase-invoices/purchase-invoice-record-totals'
import { PurchaseInvoiceRecordSections } from '@/features/purchase-invoices/purchase-invoice-record-sections'
import { lockedForSettlement } from '@/features/purchase-invoices/purchase-invoice-settlement-lock'
import { PurchaseInvoiceSettlementBadge } from '@/features/purchase-invoices/purchase-invoice-settlement-badge'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { usePurchaseInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import { usePurchaseInvoiceInlineEdit } from '@/features/purchase-invoices/use-purchase-invoice-inline-edit'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'

const TYPE_COLORS: Record<(typeof PURCHASE_INVOICE_TYPES)[number], string> = {
  proforma: BADGE_COLOR_CLASSES.blue,
  invoice: BADGE_COLOR_CLASSES.green,
}

export interface PurchaseInvoiceDetailCallbacks {
  onDelete: (numberLabel: string) => void
  /** After an in-place save, so the host refreshes what lists the document. */
  onChanged?: () => void
}

function PurchaseInvoiceHeaderActions({
  invoice,
  onDelete,
}: Pick<PurchaseInvoiceDetailCallbacks, 'onDelete'> & {
  invoice: PurchaseInvoice
}) {
  const { t } = useTranslation()

  return invoice.permissions.actions.delete === true ? (
    <Button variant="secondary" size="sm" onClick={() => onDelete(invoice.document_number ?? String(invoice.id))}>
      <Trash2 className="size-3.5" aria-hidden="true" />
      {t('purchaseInvoiceViews.detail.delete')}
    </Button>
  ) : null
}

function PurchaseInvoiceDetailBody({
  invoice,
  onChanged,
  onDelete,
}: PurchaseInvoiceDetailCallbacks & { invoice: PurchaseInvoice }) {
  const { t } = useTranslation()
  const editor = usePurchaseInvoiceInlineEdit(invoice, onChanged)

  return (
    <RecordCanvas>
      <RecordBody
        header={
          <RecordCardHeader
            media={<DetailMonogram name={invoice.supplier.name} icon={<FileText />} />}
            title={
              invoice.document_number
                ? t('purchaseInvoiceViews.detail.title', {
                    number: invoice.document_number,
                  })
                : t('purchaseInvoiceViews.detail.titleProforma')
            }
            subtitle={invoice.supplier.name}
            actions={<PurchaseInvoiceHeaderActions invoice={invoice} onDelete={onDelete} />}
            badges={
              <>
                <Badge variant="secondary" className={cn(BADGE_BASE, TYPE_COLORS[invoice.document_type])}>
                  {t(`enums.purchase_invoice_type.${invoice.document_type}`)}
                </Badge>
                {invoice.commission_settlement ? (
                  <PurchaseInvoiceSettlementBadge settlement={invoice.commission_settlement} />
                ) : null}
              </>
            }
          />
        }
      >
        {/* Providers only (no DOM): the inline editors, fields and lines, share the record form. */}
        <ResourcePermissionsProvider permissions={invoice.permissions}>
          <Form {...editor.form}>
            <RecordSectionsGrid>
              <PurchaseInvoiceRecordSections invoice={invoice} editor={editor} />
              <PurchaseInvoiceRecordTotals invoice={invoice} editor={editor} />
            </RecordSectionsGrid>
            <div className="flex flex-col gap-3 border-t p-4">
              <PurchaseInvoiceLinesRecord invoice={invoice} editor={editor} />
              <section className="flex flex-col gap-1.5">
                <h3 className="text-xs font-semibold text-foreground">
                  {t('purchaseInvoiceViews.detail.installments.title')}
                </h3>
                <PurchaseInvoiceDetailInstallments installments={invoice.installments} />
              </section>
              <section className="flex flex-col gap-1.5">
                <h3 className="text-xs font-semibold text-foreground">
                  {t('purchaseInvoiceViews.detail.orders.title')}
                </h3>
                <PurchaseInvoiceDetailOrders orders={invoice.purchase_orders} />
              </section>
              <section className="flex flex-col gap-1.5">
                <h3 className="text-xs font-semibold text-foreground">
                  {t('purchaseInvoiceViews.detail.documents.title')}
                </h3>
                <PurchaseInvoiceDocuments invoiceId={invoice.id} />
              </section>
            </div>
          </Form>
        </ResourcePermissionsProvider>
      </RecordBody>
    </RecordCanvas>
  )
}

interface PurchaseInvoiceDetailViewProps extends PurchaseInvoiceDetailCallbacks {
  invoiceId: number
}

/**
 * The passive document record (spec 0262): header and registration fields edited in
 * place one at a time, totals, then the lines (edited in place too, spec 0263),
 * installments, linked ODAs and documents.
 */
export function PurchaseInvoiceDetailView({ invoiceId, ...callbacks }: PurchaseInvoiceDetailViewProps) {
  const { t } = useTranslation()
  const { data, isError, error, refetch } = usePurchaseInvoice(invoiceId)

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('purchaseInvoiceViews.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => void refetch()}
      />
    )
  }
  if (!data) {
    return <DetailLoading />
  }
  return <PurchaseInvoiceDetailBody invoice={lockedForSettlement(data)} {...callbacks} />
}
