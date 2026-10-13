import type { UseFieldArrayReturn, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import type { VatRateRef } from '@/features/invoices/types'
import { PurchaseInvoiceEditorAddLine, type NewLineFields } from '@/features/purchase-invoices/purchase-invoice-editor-add-line'
import { PurchaseInvoiceEditorLineRow } from '@/features/purchase-invoices/purchase-invoice-editor-line-row'
import type { VatRefs } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type {
  PurchaseInvoiceLineFormValues,
  PurchaseInvoiceWriteFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'

interface PurchaseInvoiceEditorLinesSectionProps {
  form: UseFormReturn<PurchaseInvoiceWriteFormValues>
  lineArray: UseFieldArrayReturn<PurchaseInvoiceWriteFormValues, 'lines'>
  lines: PurchaseInvoiceLineFormValues[]
  vatRefs: VatRefs
  generalErrors: string[]
  onRememberVat: (ref: VatRateRef) => void
  onAddLine: (fields: NewLineFields) => void
}

/** Document rows: editable list plus the catalog/free add row; list-level server errors show here. */
export function PurchaseInvoiceEditorLinesSection({
  form,
  lineArray,
  lines,
  vatRefs,
  generalErrors,
  onRememberVat,
  onAddLine,
}: PurchaseInvoiceEditorLinesSectionProps) {
  const { t } = useTranslation()
  const listError = form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message
  const messages = listError ? [listError, ...generalErrors] : generalErrors

  return (
    <section aria-labelledby="purchase-invoice-editor-lines" className="grid gap-2 rounded-lg border bg-card p-3">
      <h3 id="purchase-invoice-editor-lines" className="text-sm font-semibold">
        {t('purchaseInvoices.editor.sections.lines')} ({t('purchaseInvoices.editor.lines.count', { count: lineArray.fields.length })})
      </h3>
      {lineArray.fields.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('purchaseInvoices.editor.lines.empty')}</p>
      ) : (
        <ul className="grid gap-2">
          {lineArray.fields.map((field, index) => (
            <PurchaseInvoiceEditorLineRow
              key={field.id}
              index={index}
              control={form.control}
              line={lines[index] ?? field}
              vatRefs={vatRefs}
              onRememberVat={onRememberVat}
              onRemove={() => lineArray.remove(index)}
            />
          ))}
        </ul>
      )}
      {messages.map((message) => (
        <p key={message} role="alert" className="text-xs text-destructive">
          {message}
        </p>
      ))}
      <PurchaseInvoiceEditorAddLine onAdd={onAddLine} onRememberVat={onRememberVat} />
    </section>
  )
}
