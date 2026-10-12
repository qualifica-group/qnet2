import { useEffect, type MutableRefObject } from 'react'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useOptionalConfirm } from '@/components/confirm-dialog-context'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { InvoiceEditorAvailableLines } from '@/features/invoices/invoice-editor-available-lines'
import { InvoiceEditorInstallments } from '@/features/invoices/invoice-editor-installments'
import { InvoiceEditorLinesSection } from '@/features/invoices/invoice-editor-lines-section'
import { NO_ID, type EditorSource } from '@/features/invoices/invoice-editor-source'
import { InvoiceEditorTotals } from '@/features/invoices/invoice-editor-totals'
import { InvoiceRecordSections } from '@/features/invoices/invoice-record-sections'
import { useInvoiceDraftDisplay } from '@/features/invoices/use-invoice-draft-display'
import { useInvoiceIssueForm } from '@/features/invoices/use-invoice-issue-form'
import type { Invoice } from '@/features/invoices/types'

interface InvoiceEditorFormProps {
  source: EditorSource
  proformaRequestId: number
  onSaved: (invoice: Invoice) => void
  onCancel: () => void
  /** Filled with the guarded close, so the dialog's own close (Esc, overlay) asks too. */
  closeRef: MutableRefObject<(() => void) | null>
}

/** Read-only context of the request being invoiced. */
function RequestContext({ source }: { source: EditorSource }) {
  const { t } = useTranslation()
  const { kind, quoteCode, note } = source.context

  if (kind === null) {
    return null
  }
  return (
    <p className="rounded-md border bg-surface px-3 py-2 text-xs text-muted-foreground">
      <span className="font-medium text-foreground">{t(`proformaRequests.kinds.${kind}`)}</span>
      {quoteCode ? ` - ${quoteCode}` : ''}
      {note ? ` - ${note}` : ''}
    </p>
  )
}

/**
 * The issue modal's body, a replica of the invoice detail (spec 0224 D-5):
 * Testata and Note as closed rows ("Fatto"/"Ripristina"), the work order's
 * available lines and the document lines grid open, the live due dates, and
 * Emetti in the footer. Leaving with changes asks first.
 */
export function InvoiceEditorForm({ source, proformaRequestId, onSaved, onCancel, closeRef }: InvoiceEditorFormProps) {
  const { t } = useTranslation()
  const confirm = useOptionalConfirm()
  const { form, lines, inline, preview, submit, isSaving } = useInvoiceIssueForm({ source, proformaRequestId, onSaved })
  const display = useInvoiceDraftDisplay(form.control, source)
  const isDirty = form.formState.isDirty

  // The dialog reads the guarded close in its own close handlers (event time, never during render).
  useEffect(() => {
    closeRef.current = () => {
      if (!isDirty || !confirm) {
        onCancel()
        return
      }
      void confirm({
        title: t('invoiceEditor.leaveConfirm.title'),
        description: t('invoiceEditor.leaveConfirm.description'),
        confirmLabel: t('invoiceEditor.leaveConfirm.confirm'),
        cancelLabel: t('invoiceEditor.leaveConfirm.cancel'),
        tone: 'warning',
      }).then((leave) => (leave ? onCancel() : undefined))
    }
    return () => {
      closeRef.current = null
    }
  }, [closeRef, confirm, isDirty, onCancel, t])

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="grid max-h-[65vh] gap-3 overflow-y-auto bg-background px-4 py-3">
          <RequestContext source={source} />
          <div className="rounded-lg border bg-card p-3">
            <RecordSectionsGrid>
              <InvoiceRecordSections
                display={display}
                control={form.control}
                inline={inline}
                companyEditable
                collectionsLocked={false}
                companyId={display.company?.id ?? NO_ID}
                bankAccounts={source.bankAccounts}
                onCompanyPicked={() => form.setValue('financial_account_id', null)}
              />
            </RecordSectionsGrid>
          </div>
          <InvoiceEditorAvailableLines
            lines={source.availableLines}
            addedQuoteLineIds={lines.addedQuoteLineIds}
            onAdd={lines.addAvailableLine}
            onAddAll={lines.addAllAvailableLines}
          />
          <InvoiceEditorLinesSection
            form={form}
            lineArray={lines.lineArray}
            lines={lines.lines}
            vatRefs={lines.vatRefs}
            onRememberVat={lines.rememberVat}
            onAddLine={lines.addLine}
          />
          <InvoiceEditorInstallments preview={preview} />
        </div>
        <DialogFooter className="flex-col items-stretch gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <InvoiceEditorTotals totals={lines.totals} />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => closeRef.current?.()}>
              {t('invoiceEditor.actions.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={isSaving}>
              {isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {t(isSaving ? 'invoiceEditor.actions.issuing' : 'invoiceEditor.actions.issue')}
            </Button>
          </div>
        </DialogFooter>
      </form>
    </Form>
  )
}
