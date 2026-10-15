import { useTranslation } from 'react-i18next'
import { EDITING_LINE_CLASS, LineEditBar } from '@/components/record-form/line-edit-bar'
import { LineRowActions, RecordLinesSection } from '@/components/record-form/record-lines'
import { useConfirmLineRemoval } from '@/components/record-form/use-confirm-line-removal'
import { editingLineIndex, lineEditingKey } from '@/components/record-form/use-lines-record-inline-edit'
import { EMPTY_AMOUNTS_CENTS } from '@/lib/amounts/line-amounts'
import { InvoiceEditorAddLine } from '@/features/invoices/invoice-editor-add-line'
import { InvoiceEditorLineRow } from '@/features/invoices/invoice-editor-line-row'
import { InvoiceLinesTable } from '@/features/invoices/invoice-lines-table'
import type { InvoiceWithPermissions } from '@/features/invoices/types'
import type { InvoiceRecordEditor } from '@/features/invoices/use-invoice-inline-edit'

interface InvoiceLinesRecordProps {
  invoice: InvoiceWithPermissions
  editor: InvoiceRecordEditor
}

/**
 * "Righe" on the invoice record (spec 0263): the pencil opens a line in place
 * and Salva sends the whole set; the trash asks and deletes right away;
 * "Aggiungi riga" picks a catalog or free line and opens it. Only a proforma
 * the actor may update changes (otherwise the table is read-only).
 */
export function InvoiceLinesRecord({ invoice, editor }: InvoiceLinesRecordProps) {
  const { t } = useTranslation()
  const { form, inline, lines, lineAmounts, vatRefs, rememberVat, addLine, removeLine } = editor
  const canWrite = invoice.type === 'proforma' && invoice.permissions.resource.update
  const askRemove = useConfirmLineRemoval(removeLine)
  const openIndex = editingLineIndex(inline.editingField)
  const openLine = openIndex === null ? undefined : lines[openIndex]
  const idle = canWrite && !inline.isSaving

  return (
    <RecordLinesSection
      title={t('invoices.detail.lines')}
      canWrite={canWrite}
      inline={inline}
      addPanel={(close) => (
        <InvoiceEditorAddLine
          onAdd={(fields) => {
            close()
            addLine(fields)
          }}
          onRememberVat={rememberVat}
        />
      )}
    >
      <InvoiceLinesTable
        lines={invoice.lines}
        editing={
          canWrite
            ? {
                index: openIndex,
                row:
                  openIndex === null || openLine === undefined ? null : (
                    <InvoiceEditorLineRow
                      index={openIndex}
                      control={form.control}
                      line={openLine}
                      amounts={lineAmounts[openIndex] ?? EMPTY_AMOUNTS_CENTS}
                      vatRefs={vatRefs}
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
