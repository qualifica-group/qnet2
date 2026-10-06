import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InvoiceDocumentSections } from '@/features/invoices/invoice-document-sections'
import {
  InvoiceTagBadge,
  InvoiceTypeBadge,
  PaymentStatusIndicator,
} from '@/features/invoices/invoice-status-badges'
import { useInvoice } from '@/features/invoices/use-invoice-queries'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

function InvoiceDetailBody({ invoice, onChanged }: { invoice: InvoiceWithPermissions; onChanged: () => void }) {
  const { t } = useTranslation()
  const externalReference = invoice.external_number
    ? `${invoice.external_number} - ${formatDate(invoice.external_date)}`
    : null

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={invoice.number_label} icon={<FileText />} />}
          title={t('invoices.detail.title', { number: invoice.number_label })}
          subtitle={invoice.customer.name}
          badges={
            <>
              <InvoiceTypeBadge type={invoice.type} />
              {invoice.tag ? <InvoiceTagBadge tag={invoice.tag} /> : null}
              <PaymentStatusIndicator status={invoice.payment_status} />
            </>
          }
        />
        <RecordSectionsGrid>
          <RecordSection title={t('invoices.detail.header')}>
            <RecordFieldList>
              <RecordField label={t('invoices.columns.document_date')}>{formatDate(invoice.document_date)}</RecordField>
              <RecordField label={t('invoices.columns.company')}>{invoice.company.name}</RecordField>
              <RecordField label={t('invoices.columns.payment_method')}>{invoice.payment_method.name}</RecordField>
              <RecordField label={t('invoices.detail.externalReference')}>
                {externalReference ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('invoices.columns.work_order_code')}>
                {invoice.work_order?.code ?? <DetailEmpty />}
              </RecordField>
              <RecordField label={t('invoices.columns.quote_code')}>{invoice.quote?.code ?? <DetailEmpty />}</RecordField>
              <RecordField label={t('invoices.detail.createdBy')}>{invoice.created_by.name}</RecordField>
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('invoices.footer.label')}>
            <RecordFieldList>
              <RecordField label={t('invoices.columns.net_amount')}>{formatEuro(invoice.net_amount)}</RecordField>
              <RecordField label={t('invoices.columns.vat_amount')}>{formatEuro(invoice.vat_amount)}</RecordField>
              <RecordField label={t('invoices.columns.total_amount')}>{formatEuro(invoice.total_amount)}</RecordField>
              <RecordField label={t('invoices.columns.collected_amount')}>{formatEuro(invoice.collected_amount)}</RecordField>
              <RecordField label={t('invoices.columns.residual_amount')}>{formatEuro(invoice.residual_amount)}</RecordField>
              <RecordField label={t('invoices.columns.deviation')}>
                {invoice.deviation === null ? <DetailEmpty /> : formatEuro(invoice.deviation)}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('invoices.detail.notes')} full>
            <RecordFieldList>
              <RecordField label={t('invoices.detail.notes')}>
                {invoice.notes ? <span className="whitespace-pre-wrap">{invoice.notes}</span> : <DetailEmpty />}
              </RecordField>
              <RecordField label={t('invoices.detail.internalNote')}>
                {invoice.internal_note ? (
                  <span className="whitespace-pre-wrap">{invoice.internal_note}</span>
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
        <div className="border-t p-4">
          <InvoiceDocumentSections invoice={invoice} onChanged={onChanged} />
        </div>
      </RecordCard>
    </RecordCanvas>
  )
}

interface InvoiceDetailViewProps {
  invoiceId: number
  /** Called after a collection changed the document (the caller refreshes its grid). */
  onChanged: () => void
}

/** Full read-only view of a document: header, totals, lines and installments. */
export function InvoiceDetailView({ invoiceId, onChanged }: InvoiceDetailViewProps) {
  const { t } = useTranslation()
  const { data, isError, refetch } = useInvoice(invoiceId)

  if (isError) {
    return (
      <DetailError message={t('invoices.detail.loadError')} retryLabel={t('common.retry')} onRetry={() => void refetch()} />
    )
  }
  if (!data) {
    return <DetailLoading />
  }
  return <InvoiceDetailBody invoice={data} onChanged={onChanged} />
}
