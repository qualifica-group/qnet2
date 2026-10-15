import { useTranslation } from 'react-i18next'
import { EDITING_LINE_CLASS, LineEditBar } from '@/components/record-form/line-edit-bar'
import { LineRowActions, RecordLinesSection } from '@/components/record-form/record-lines'
import { useConfirmLineRemoval } from '@/components/record-form/use-confirm-line-removal'
import { editingLineIndex, lineEditingKey } from '@/components/record-form/use-lines-record-inline-edit'
import { EMPTY_AMOUNTS_CENTS } from '@/lib/amounts/line-amounts'
import { PurchaseInvoiceDetailLines } from '@/features/purchase-invoices/purchase-invoice-detail-lines'
import { PurchaseInvoiceEditorAddLine } from '@/features/purchase-invoices/purchase-invoice-editor-add-line'
import { PurchaseInvoiceEditorLineRow } from '@/features/purchase-invoices/purchase-invoice-editor-line-row'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import type { PurchaseInvoiceRecordEditor } from '@/features/purchase-invoices/use-purchase-invoice-inline-edit'

interface PurchaseInvoiceLinesRecordProps {
  invoice: PurchaseInvoice
  editor: PurchaseInvoiceRecordEditor
}

/**
 * "Righe" on the passive invoice record (spec 0263): the pencil opens a line
 * in place and Salva sends the whole set; the trash asks and deletes right
 * away; "Aggiungi riga" picks a catalog or free line and opens it. Only a
 * proforma the actor may update changes (otherwise the table is read-only).
 */
export function PurchaseInvoiceLinesRecord({ invoice, editor }: PurchaseInvoiceLinesRecordProps) {
  const { t } = useTranslation()
  const { form, inline, lineAmounts, rememberVat, addLine, removeLine } = editor
  const canWrite = invoice.permissions.actions.update === true
  const askRemove = useConfirmLineRemoval(removeLine)
  const openIndex = editingLineIndex(inline.editingField)
  const idle = canWrite && !inline.isSaving

  return (
    <RecordLinesSection
      title={t('purchaseInvoiceViews.detail.lines.title')}
      canWrite={canWrite}
      inline={inline}
      addPanel={(close) => (
        <PurchaseInvoiceEditorAddLine
          onAdd={(fields) => {
            close()
            addLine(fields)
          }}
          onRememberVat={rememberVat}
        />
      )}
    >
      <PurchaseInvoiceDetailLines
        lines={invoice.lines}
        editing={
          canWrite
            ? {
                index: openIndex,
                row:
                  openIndex === null ? null : (
                    <PurchaseInvoiceEditorLineRow
                      index={openIndex}
                      control={form.control}
                      amounts={lineAmounts[openIndex] ?? EMPTY_AMOUNTS_CENTS}
                      onRememberVat={rememberVat}
                      className={EDITING_LINE_CLASS}
                      footer={<LineEditBar inline={inline} />}
                    />
                  ),
                actions: (index) => (
                  <LineRowActions
                    n={index + 1}
                    onEdit={idle ? () => inline.start(lineEditingKey(index)) : undefined}
                    onRemove={idle && invoice.lines.length > 1 ? () => void askRemove(index) : undefined}
                  />
                ),
              }
            : undefined
        }
      />
    </RecordLinesSection>
  )
}
