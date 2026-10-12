import { useTranslation } from 'react-i18next'
import { Calculator, FileDown, FileText, Stamp } from 'lucide-react'
import { DetailEmpty, DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InvoiceDetailLines } from '@/features/invoices/invoice-detail-lines'
import { InvoiceEditorInstallments } from '@/features/invoices/invoice-editor-installments'
import { InvoiceDeviationField, InvoiceExternalReferenceFields } from '@/features/invoices/invoice-fields'
import { InvoiceRecordSections } from '@/features/invoices/invoice-record-sections'
import { useInvoiceInlineEdit } from '@/features/invoices/use-invoice-inline-edit'
import {
  InvoiceTagBadge,
  InvoiceTypeBadge,
  PaymentStatusIndicator,
} from '@/features/invoices/invoice-status-badges'
import { useInvoicePdfDownload } from '@/features/invoices/use-invoice-pdf-download'
import { useOutboundEmailsTab } from '@/features/outbound-emails/use-outbound-emails-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useInvoice } from '@/features/invoices/use-invoice-queries'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

/** "Nuova email" of a document attaches its PDF (spec 0195 D-12). */
const INVOICE_DRAFT_PAYLOAD = { attach_pdf: true }

function InvoiceDetailBody({ invoice, onChanged }: { invoice: InvoiceWithPermissions; onChanged: () => void }) {
  const { t } = useTranslation()
  const { download, isDownloading } = useInvoicePdfDownload()
  const editor = useInvoiceInlineEdit(invoice, onChanged)
  const { form, inline, preview } = editor
  const emailsTab = useOutboundEmailsTab(
    { type: 'invoices', id: invoice.id },
    invoice.permissions.actions.view_emails,
    invoice.permissions.actions.send_email,
    INVOICE_DRAFT_PAYLOAD,
  )
  const tabs: RecordCollaborationTab[] = emailsTab ? [emailsTab] : []
  const externalReference = invoice.external_number
    ? `${invoice.external_number} - ${formatDate(invoice.external_date)}`
    : null

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={invoice.number_label} icon={<FileText />} />}
            title={t('invoices.detail.title', { number: invoice.number_label })}
            subtitle={invoice.customer.name}
            actions={
              <Button variant="secondary" size="sm" disabled={isDownloading} onClick={() => void download(invoice.id)}>
                <FileDown className="size-3.5" aria-hidden="true" />
                {t('invoices.actions.pdf')}
              </Button>
            }
            badges={
              <>
                <InvoiceTypeBadge type={invoice.type} />
                {invoice.tag ? <InvoiceTagBadge tag={invoice.tag} /> : null}
                <PaymentStatusIndicator status={invoice.payment_status} />
              </>
            }
          />
        }
      >
        {/* Provider only (no DOM): the inline editors share the document form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <InvoiceRecordSections
              display={{
                documentDate: invoice.document_date,
                company: invoice.company,
                customer: invoice.customer,
                paymentMethod: invoice.payment_method,
                financialAccount: invoice.financial_account,
                layout: invoice.layout,
                tag: invoice.tag,
                notes: invoice.notes,
                internalNote: invoice.internal_note,
              }}
              control={form.control}
              inline={inline}
              companyEditable={false}
              collectionsLocked={invoice.has_collections}
              companyId={invoice.company.id}
              bankAccounts={null}
              schedulePreview={<InvoiceEditorInstallments preview={preview} />}
            />
            <RecordSection title={t('invoices.detail.registration')} icon={<Stamp />}>
              <RecordFieldList>
                <RecordInlineField
                  field="external_number"
                  label={t('invoices.detail.externalReference')}
                  inline={inline}
                  editor={<InvoiceExternalReferenceFields control={form.control} />}
                >
                  {externalReference ?? <DetailEmpty />}
                </RecordInlineField>
                <RecordField label={t('invoices.columns.work_order_code')}>
                  {invoice.work_order?.code ?? <DetailEmpty />}
                </RecordField>
                <RecordField label={t('invoices.columns.quote_code')}>{invoice.quote?.code ?? <DetailEmpty />}</RecordField>
                <RecordField label={t('invoices.detail.createdBy')}>{invoice.created_by.name}</RecordField>
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('invoices.footer.label')} icon={<Calculator />}>
              <RecordFieldList>
                <RecordField label={t('invoices.columns.net_amount')}>{formatEuro(invoice.net_amount)}</RecordField>
                <RecordField label={t('invoices.columns.vat_amount')}>{formatEuro(invoice.vat_amount)}</RecordField>
                <RecordField label={t('invoices.columns.total_amount')}>{formatEuro(invoice.total_amount)}</RecordField>
                <RecordField label={t('invoices.columns.collected_amount')}>{formatEuro(invoice.collected_amount)}</RecordField>
                <RecordField label={t('invoices.columns.residual_amount')}>{formatEuro(invoice.residual_amount)}</RecordField>
                <RecordInlineField
                  field="deviation"
                  label={t('invoices.columns.deviation')}
                  inline={inline}
                  editor={<InvoiceDeviationField control={form.control} />}
                >
                  {invoice.deviation === null ? <DetailEmpty /> : formatEuro(invoice.deviation)}
                </RecordInlineField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
          <InvoiceDetailLines invoice={invoice} editor={editor} onChanged={onChanged} />
        </Form>
      </RecordBody>
    </RecordCanvas>
  )
}

interface InvoiceDetailViewProps {
  invoiceId: number
  /** Called after an in-place save or a collection changed the document (the caller refreshes its grid). */
  onChanged: () => void
}

/**
 * A document's detail: header, totals, lines and installments. There is no
 * edit dialog (spec 0224): the rows edit in place, one at a time.
 */
export function InvoiceDetailView({ invoiceId, onChanged }: InvoiceDetailViewProps) {
  const { t } = useTranslation()
  const { data, isError, error, refetch } = useInvoice(invoiceId)

  if (isError) {
    return (
      <DetailError error={error} message={t('invoices.detail.loadError')} retryLabel={t('common.retry')} onRetry={() => void refetch()} />
    )
  }
  if (!data) {
    return <DetailLoading />
  }
  // The rows read the field permissions: the provider wraps the whole body.
  return (
    <ResourcePermissionsProvider permissions={data.permissions}>
      <InvoiceDetailBody invoice={data} onChanged={onChanged} />
    </ResourcePermissionsProvider>
  )
}
