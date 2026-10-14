import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FileDown, FileText, ListOrdered, Wallet } from 'lucide-react'
import { DetailError, DetailLoading, DetailMonogram } from '@/components/detail/detail-panel'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
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
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InvoiceDocumentSections } from '@/features/invoices/invoice-document-sections'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import { InvoiceRecordSections } from '@/features/invoices/invoice-record-sections'
import {
  InvoiceTagBadge,
  InvoiceTypeBadge,
  PaymentStatusIndicator,
} from '@/features/invoices/invoice-status-badges'
import { useInvoiceInlineEdit } from '@/features/invoices/use-invoice-inline-edit'
import { useInvoicePdfDownload } from '@/features/invoices/use-invoice-pdf-download'
import { useOutboundEmailsTab } from '@/features/outbound-emails/use-outbound-emails-tab'
import { useInvoice } from '@/features/invoices/use-invoice-queries'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

/** "Nuova email" of a document attaches its PDF (spec 0195 D-12). */
const INVOICE_DRAFT_PAYLOAD = { attach_pdf: true }

const TOTAL_FIELDS = ['net_amount', 'vat_amount', 'total_amount', 'collected_amount', 'residual_amount'] as const

function InvoiceDetailBody({ invoice, onChanged }: { invoice: InvoiceWithPermissions; onChanged: () => void }) {
  const { t } = useTranslation()
  const { download, isDownloading } = useInvoicePdfDownload()
  const editor = useInvoiceInlineEdit(invoice, onChanged)
  const [editingLines, setEditingLines] = useState(false)
  const emailsTab = useOutboundEmailsTab(
    { type: 'invoices', id: invoice.id },
    invoice.permissions.actions.view_emails,
    invoice.permissions.actions.send_email,
    INVOICE_DRAFT_PAYLOAD,
  )
  const tabs: RecordCollaborationTab[] = emailsTab ? [emailsTab] : []
  // Lines and schedule change only on a proforma, in the editor that previews the installments (spec 0261 D-4).
  const canEditLines = invoice.type === 'proforma' && invoice.permissions.resource.update

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
        <ResourcePermissionsProvider permissions={invoice.permissions}>
          {/* Provider only (no DOM): the inline editors share the record form. */}
          <Form {...editor.form}>
            <RecordSectionsGrid>
              <InvoiceRecordSections invoice={invoice} editor={editor} />
              <RecordSection title={t('invoices.footer.label')} icon={<Wallet />}>
                <RecordFieldList>
                  {TOTAL_FIELDS.map((field) => (
                    <RecordField key={field} label={t(`invoices.columns.${field}`)}>
                      {formatEuro(invoice[field])}
                    </RecordField>
                  ))}
                </RecordFieldList>
              </RecordSection>
            </RecordSectionsGrid>
          </Form>
        </ResourcePermissionsProvider>
        <div className="flex flex-col gap-3 border-t p-4">
          {canEditLines ? (
            <div className="flex justify-end">
              <Button type="button" variant="secondary" size="xs" onClick={() => setEditingLines(true)}>
                <ListOrdered aria-hidden="true" />
                {t('invoices.record.editLines')}
              </Button>
            </div>
          ) : null}
          <InvoiceDocumentSections invoice={invoice} onChanged={onChanged} />
        </div>
      </RecordBody>
      {editingLines ? (
        <InvoiceEditorDialog
          mode="edit"
          invoiceId={invoice.id}
          open
          onClose={() => setEditingLines(false)}
          onSaved={() => {
            setEditingLines(false)
            onChanged()
          }}
        />
      ) : null}
    </RecordCanvas>
  )
}

interface InvoiceDetailViewProps {
  invoiceId: number
  /** Called after a save or a collection changed the document (the caller refreshes its grid). */
  onChanged: () => void
}

/**
 * The invoice record (spec 0261): header and registration fields edited in
 * place one at a time, totals, then lines and installments with their
 * collections; lines change in the editor dialog ("Modifica righe").
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
  // Remount on every server change so the form restarts from the saved document.
  return <InvoiceDetailBody key={`${data.id}|${data.updated_at}|${data.type}`} invoice={data} onChanged={onChanged} />
}
