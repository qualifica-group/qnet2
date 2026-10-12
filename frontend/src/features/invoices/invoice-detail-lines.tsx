import { useTranslation } from 'react-i18next'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { InvoiceEditorInstallments } from '@/features/invoices/invoice-editor-installments'
import { InvoiceEditorLinesSection } from '@/features/invoices/invoice-editor-lines-section'
import { InvoiceEditorTotals } from '@/features/invoices/invoice-editor-totals'
import { InvoiceInstallmentsSection } from '@/features/invoices/invoice-installments-section'
import { InvoiceLinesTable } from '@/features/invoices/invoice-lines-table'
import type { InvoiceDetailEditor } from '@/features/invoices/use-invoice-inline-edit'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

interface InvoiceDetailLinesProps {
  invoice: InvoiceWithPermissions
  editor: InvoiceDetailEditor
  onChanged?: () => void
}

/**
 * The record card's closing band (spec 0224 D-4, the Offerte model): the
 * document lines as ONE in-place row across the band — its pencil swaps the
 * read-only table for the issue grid with live totals and the due dates the
 * save would produce — then the persisted due dates with their collections.
 */
export function InvoiceDetailLines({ invoice, editor, onChanged }: InvoiceDetailLinesProps) {
  const { t } = useTranslation()
  const { form, lines, preview, inline } = editor

  return (
    <div className="flex min-w-0 flex-col gap-3 border-t p-4">
      <RecordInlineField
        field="lines"
        label={t('invoices.detail.lines')}
        layout="block"
        inline={inline}
        editor={
          <div className="grid gap-3">
            <InvoiceEditorLinesSection
              form={form}
              lineArray={lines.lineArray}
              lines={lines.lines}
              vatRefs={lines.vatRefs}
              onRememberVat={lines.rememberVat}
              onAddLine={lines.addLine}
            />
            <InvoiceEditorTotals totals={lines.totals} />
            <InvoiceEditorInstallments preview={preview} />
          </div>
        }
      >
        <div className="flex flex-col gap-1.5">
          <h3 className="text-xs font-semibold text-foreground">{t('invoices.detail.lines')}</h3>
          <InvoiceLinesTable lines={invoice.lines} />
        </div>
      </RecordInlineField>
      <InvoiceInstallmentsSection invoice={invoice} onChanged={onChanged} />
    </div>
  )
}
