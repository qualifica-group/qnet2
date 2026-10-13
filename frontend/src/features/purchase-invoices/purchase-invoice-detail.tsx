import { useTranslation } from 'react-i18next'
import { FileText, FileUser, Pencil, Trash2 } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatEuro } from '@/features/invoices/invoice-format'
import { PURCHASE_INVOICE_TYPES } from '@/features/purchase-invoice-installments/types'
import { PurchaseInvoiceDetailInstallments } from '@/features/purchase-invoices/purchase-invoice-detail-installments'
import { PurchaseInvoiceDetailLines } from '@/features/purchase-invoices/purchase-invoice-detail-lines'
import { PurchaseInvoiceDetailOrders } from '@/features/purchase-invoices/purchase-invoice-detail-orders'
import { PurchaseInvoiceDocuments } from '@/features/purchase-invoices/purchase-invoice-documents'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { usePurchaseInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'

const TYPE_COLORS: Record<(typeof PURCHASE_INVOICE_TYPES)[number], string> = {
  proforma: BADGE_COLOR_CLASSES.blue,
  invoice: BADGE_COLOR_CLASSES.green,
}

export interface PurchaseInvoiceDetailCallbacks {
  onEdit: () => void
  onDetails: () => void
  onDelete: (numberLabel: string) => void
}

function PurchaseInvoiceHeaderActions({ invoice, onEdit, onDetails, onDelete }: PurchaseInvoiceDetailCallbacks & { invoice: PurchaseInvoice }) {
  const { t } = useTranslation()
  const { actions } = invoice.permissions

  return (
    <>
      {actions.update === true ? (
        <Button variant="secondary" size="sm" onClick={onEdit}>
          <Pencil className="size-3.5" aria-hidden="true" />
          {t('purchaseInvoiceViews.detail.edit')}
        </Button>
      ) : null}
      {actions.details === true ? (
        <Button variant="secondary" size="sm" onClick={onDetails}>
          <FileUser className="size-3.5" aria-hidden="true" />
          {t('purchaseInvoiceViews.detail.details')}
        </Button>
      ) : null}
      {actions.delete === true ? (
        <Button variant="secondary" size="sm" onClick={() => onDelete(invoice.document_number ?? String(invoice.id))}>
          <Trash2 className="size-3.5" aria-hidden="true" />
          {t('purchaseInvoiceViews.detail.delete')}
        </Button>
      ) : null}
    </>
  )
}

function PurchaseInvoiceDetailBody({ invoice, ...callbacks }: PurchaseInvoiceDetailCallbacks & { invoice: PurchaseInvoice }) {
  const { t } = useTranslation()

  return (
    <RecordCanvas>
      <RecordBody
        header={
          <RecordCardHeader
            media={<DetailMonogram name={invoice.supplier.name} icon={<FileText />} />}
            title={
              invoice.document_number
                ? t('purchaseInvoiceViews.detail.title', { number: invoice.document_number })
                : t('purchaseInvoiceViews.detail.titleProforma')
            }
            subtitle={invoice.supplier.name}
            actions={<PurchaseInvoiceHeaderActions invoice={invoice} {...callbacks} />}
            badges={
              <Badge variant="secondary" className={cn(BADGE_BASE, TYPE_COLORS[invoice.document_type])}>
                {t(`enums.purchase_invoice_type.${invoice.document_type}`)}
              </Badge>
            }
          />
        }
      >
        <RecordSectionsGrid>
          <RecordSection title={t('purchaseInvoiceViews.detail.header')}>
            <RecordFieldList>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.document_date')}>
                {formatDate(invoice.document_date)}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.company')}>{invoice.company.name}</RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.supplier')}>{invoice.supplier.name}</RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.payment_method')}>
                {invoice.payment_method.name}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.financial_account')}>
                {invoice.financial_account?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.company_site')}>
                {invoice.company_site?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.operational_site')}>
                {invoice.operational_site?.name ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.cost_type')}>
                {invoice.cost_type ? (
                  <CostTypeBadge name={invoice.cost_type.name} color={invoice.cost_type.color} />
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.work_order')}>
                {invoice.work_order?.code ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.detail.fields.created_by')}>
                {invoice.created_by.name}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('purchaseInvoiceViews.detail.totals')}>
            <RecordFieldList>
              <RecordField label={t('purchaseInvoiceViews.columns.net_amount')}>{formatEuro(invoice.net_amount)}</RecordField>
              <RecordField label={t('purchaseInvoiceViews.columns.vat_amount')}>{formatEuro(invoice.vat_amount)}</RecordField>
              <RecordField label={t('purchaseInvoiceViews.columns.total_amount')}>
                {formatEuro(invoice.total_amount)}
              </RecordField>
              <RecordField label={t('purchaseInvoiceViews.columns.paid_amount')}>{formatEuro(invoice.paid_amount)}</RecordField>
              <RecordField label={t('purchaseInvoiceViews.columns.residual_amount')}>
                {formatEuro(invoice.residual_amount)}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('purchaseInvoiceViews.detail.notes')} full>
            {invoice.notes ? <span className="whitespace-pre-wrap text-sm">{invoice.notes}</span> : <DetailEmpty />}
          </RecordSection>
        </RecordSectionsGrid>
        <div className="flex flex-col gap-3 border-t p-4">
          <section className="flex flex-col gap-1.5">
            <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.lines.title')}</h3>
            <PurchaseInvoiceDetailLines lines={invoice.lines} />
          </section>
          <section className="flex flex-col gap-1.5">
            <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.installments.title')}</h3>
            <PurchaseInvoiceDetailInstallments installments={invoice.installments} />
          </section>
          <section className="flex flex-col gap-1.5">
            <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.orders.title')}</h3>
            <PurchaseInvoiceDetailOrders orders={invoice.purchase_orders} />
          </section>
          <section className="flex flex-col gap-1.5">
            <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.documents.title')}</h3>
            <PurchaseInvoiceDocuments invoiceId={invoice.id} />
          </section>
        </div>
      </RecordBody>
    </RecordCanvas>
  )
}

interface PurchaseInvoiceDetailViewProps extends PurchaseInvoiceDetailCallbacks {
  invoiceId: number
}

/** Full read-only view of a passive document: header, totals, lines, installments, linked ODAs and documents. */
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
  return <PurchaseInvoiceDetailBody invoice={data} {...callbacks} />
}
