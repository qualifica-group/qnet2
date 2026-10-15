import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { DialogFooter } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { PurchaseInvoiceEditorHeaderSection } from '@/features/purchase-invoices/purchase-invoice-editor-header-section'
import { PurchaseInvoiceEditorInstallments } from '@/features/purchase-invoices/purchase-invoice-editor-installments'
import { PurchaseInvoiceEditorLinesSection } from '@/features/purchase-invoices/purchase-invoice-editor-lines-section'
import { PurchaseInvoiceEditorOrdersSection } from '@/features/purchase-invoices/purchase-invoice-editor-orders-section'
import type { EditorSource } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import { PurchaseInvoiceEditorTotals } from '@/features/purchase-invoices/purchase-invoice-editor-totals'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { usePurchaseInvoiceEditorForm } from '@/features/purchase-invoices/use-purchase-invoice-editor-form'

interface PurchaseInvoiceEditorFormProps {
  source: EditorSource
  onSaved: (invoice: PurchaseInvoice) => void
  onCancel: () => void
}

/** The whole dialog body: sections, totals and the footer actions. Mounted once the source is loaded. */
export function PurchaseInvoiceEditorForm({ source, onSaved, onCancel }: PurchaseInvoiceEditorFormProps) {
  const { t } = useTranslation()
  const editor = usePurchaseInvoiceEditorForm({ source, onSaved })
  const partiesChosen = !Number.isNaN(editor.supplierId) && !Number.isNaN(editor.companyId)

  return (
    <Form {...editor.form}>
      <form onSubmit={editor.submit} noValidate className="flex min-h-0 flex-1 flex-col">
        <div className="grid max-h-[65vh] gap-3 overflow-y-auto px-4 py-3">
          <PurchaseInvoiceEditorHeaderSection
            form={editor.form}
            source={source}
            companyId={editor.companyId}
            documentType={editor.documentType}
            partiesLocked={editor.orderIds.length > 0}
          />
          {partiesChosen ? (
            <PurchaseInvoiceEditorOrdersSection
              supplierId={editor.supplierId}
              companyId={editor.companyId}
              linkedOrderIds={editor.orderIds}
              isAdding={editor.isAddingOrder}
              notice={editor.ordersNotice}
              onAdd={editor.addOrder}
            />
          ) : null}
          {editor.showReceiptNotice ? (
            <p role="note" className="rounded-md border bg-surface px-3 py-2 text-xs text-muted-foreground">
              {t('purchaseInvoices.editor.receiptNotice')}
            </p>
          ) : null}
          <PurchaseInvoiceEditorLinesSection
            form={editor.form}
            lineArray={editor.lineArray}
            lineAmounts={editor.lineAmounts}
            generalErrors={editor.generalErrors}
            onRememberVat={editor.rememberVat}
            onAddLine={editor.addLine}
          />
          <PurchaseInvoiceEditorInstallments preview={editor.preview} />
        </div>
        <DialogFooter className="flex-col items-stretch gap-2 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <PurchaseInvoiceEditorTotals totals={editor.totals} />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={onCancel}>
              {t('purchaseInvoices.editor.actions.cancel')}
            </Button>
            <Button type="submit" size="sm" disabled={editor.isSaving}>
              {editor.isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {t(editor.isSaving ? 'purchaseInvoices.editor.actions.saving' : 'purchaseInvoices.editor.actions.save')}
            </Button>
          </div>
        </DialogFooter>
      </form>
    </Form>
  )
}
