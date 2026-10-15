import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { InvoiceEditorAvailableLines } from '@/features/invoices/invoice-editor-available-lines'
import { InvoiceEditorHeaderSection } from '@/features/invoices/invoice-editor-header-section'
import { InvoiceEditorInstallments } from '@/features/invoices/invoice-editor-installments'
import { InvoiceEditorLinesSection } from '@/features/invoices/invoice-editor-lines-section'
import type { EditorSource } from '@/features/invoices/invoice-editor-source'
import { InvoiceEditorTotals } from '@/features/invoices/invoice-editor-totals'
import { useInvoiceEditorForm } from '@/features/invoices/use-invoice-editor-form'
import type { Invoice } from '@/features/invoices/types'

interface InvoiceEditorFormProps {
  source: EditorSource
  proformaRequestId: number
  onSaved: (invoice: Invoice) => void
  onCancel: () => void
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

/** The whole modal body: sections, totals and the footer actions. Mounted once the source is loaded. */
export function InvoiceEditorForm({ source, proformaRequestId, onSaved, onCancel }: InvoiceEditorFormProps) {
  const { t } = useTranslation()
  const editor = useInvoiceEditorForm({ source, proformaRequestId, onSaved })

  return (
    <Form {...editor.form}>
      <form onSubmit={editor.submit} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="grid max-h-[65vh] gap-3 overflow-y-auto px-4 py-3">
          <RequestContext source={source} />
          <InvoiceEditorHeaderSection form={editor.form} source={source} companyId={editor.companyId} />
          <InvoiceEditorAvailableLines
            lines={source.availableLines}
            addedQuoteLineIds={editor.addedQuoteLineIds}
            onAdd={editor.addAvailableLine}
            onAddAll={editor.addAllAvailableLines}
          />
          <InvoiceEditorLinesSection
            form={editor.form}
            lineArray={editor.lineArray}
            lines={editor.lines}
            lineAmounts={editor.lineAmounts}
            vatRefs={editor.vatRefs}
            onRememberVat={editor.rememberVat}
            onAddLine={editor.addLine}
          />
          <InvoiceEditorInstallments preview={editor.preview} />
        </div>
        <DialogFooter className="flex-col items-stretch gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <InvoiceEditorTotals totals={editor.totals} />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={onCancel}>
              {t('invoiceEditor.actions.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={editor.isSaving}>
              {editor.isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {t(editor.isSaving ? 'invoiceEditor.actions.issuing' : 'invoiceEditor.actions.issue')}
            </Button>
          </div>
        </DialogFooter>
      </form>
    </Form>
  )
}
