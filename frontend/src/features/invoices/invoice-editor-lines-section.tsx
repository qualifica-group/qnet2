import type { UseFieldArrayReturn, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { InvoiceEditorAddLine } from '@/features/invoices/invoice-editor-add-line'
import { InvoiceEditorLineRow, LINE_GRID_CLASS } from '@/features/invoices/invoice-editor-line-row'
import type { InvoiceLineValues, NewLineFields, VatRefs } from '@/features/invoices/invoice-editor-lines'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import type { VatRateRef } from '@/features/invoices/types'

interface InvoiceEditorLinesSectionProps {
  form: UseFormReturn<InvoiceWriteFormValues>
  lineArray: UseFieldArrayReturn<InvoiceWriteFormValues, 'lines'>
  lines: InvoiceLineValues[]
  vatRefs: VatRefs
  onRememberVat: (ref: VatRateRef) => void
  onAddLine: (fields: NewLineFields) => void
}

/** Document rows: editable list plus the catalog/free add row. */
export function InvoiceEditorLinesSection({
  form,
  lineArray,
  lines,
  vatRefs,
  onRememberVat,
  onAddLine,
}: InvoiceEditorLinesSectionProps) {
  const { t } = useTranslation()
  const listError = form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message

  return (
    <section aria-labelledby="invoice-editor-lines" className="grid gap-2 rounded-lg border bg-card p-3">
      <h3 id="invoice-editor-lines" className="text-sm font-semibold">
        {t('invoiceEditor.sections.documentLines')} ({t('invoiceEditor.lines.count', { count: lineArray.fields.length })})
      </h3>
      {lineArray.fields.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('invoiceEditor.lines.empty')}</p>
      ) : (
        <>
          <div aria-hidden="true" className={`hidden gap-2 px-2 text-xs text-muted-foreground lg:grid ${LINE_GRID_CLASS}`}>
            <span>{t('invoiceEditor.lines.description')}</span>
            <span className="text-right">{t('invoiceEditor.lines.quantity')}</span>
            <span className="text-right">{t('invoiceEditor.lines.unitPrice')}</span>
            <span>{t('invoiceEditor.lines.vatRate')}</span>
            <span className="text-right">{t('invoiceEditor.lines.net')}</span>
            <span className="text-right">{t('invoiceEditor.lines.total')}</span>
            <span />
          </div>
          <ul className="grid gap-2">
            {lineArray.fields.map((field, index) => (
              <InvoiceEditorLineRow
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
        </>
      )}
      {listError ? (
        <p role="alert" className="text-xs text-destructive">
          {listError}
        </p>
      ) : null}
      <InvoiceEditorAddLine onAdd={onAddLine} onRememberVat={onRememberVat} />
    </section>
  )
}
