import { useTranslation } from 'react-i18next'
import { ListOrdered, Plus } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { useConfirm } from '@/components/confirm-dialog-context'
import { EDITING_LINE_CLASS, LineEditBar } from '@/components/record-form/line-edit-bar'
import { lineEditingKey } from '@/components/record-form/use-lines-record-inline-edit'
import { Button } from '@/components/ui/button'
import { TooltipProvider } from '@/components/ui/tooltip'
import { previewTotals } from '@/features/purchase-requests/purchase-request-amounts'
import { PurchaseOrderLineRow } from '@/features/purchase-orders/purchase-order-line-row'
import { PurchaseOrderLineView } from '@/features/purchase-orders/purchase-order-line-view'
import { PurchaseOrderLinesHeader } from '@/features/purchase-orders/purchase-order-lines-header'
import { canEditOrderLines } from '@/features/purchase-orders/purchase-order-permissions'
import { PurchaseOrderTotals } from '@/features/purchase-orders/purchase-order-totals'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import type { PurchaseOrderDetailEditor } from '@/features/purchase-orders/use-purchase-order-inline-edit'

interface PurchaseOrderLinesRecordProps {
  order: PurchaseOrder
  editor: PurchaseOrderDetailEditor
}

/**
 * The ODA lines on the detail (spec 0257 D-7): each saved line is a read-only
 * band; its pencil opens the editable row in its place, and Salva sends that
 * line alone (the other lines travel unchanged). "Aggiungi riga" opens a new
 * line the same way; the trash asks and deletes right away. Outside draft /
 * in validation every line stays read-only.
 */
export function PurchaseOrderLinesRecord({ order, editor }: PurchaseOrderLinesRecordProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { form, lineArray, lines, inline, startNewLine, removeLine } = editor
  const canWrite = canEditOrderLines(order)

  const askRemove = async (index: number) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('purchaseOrderForm.lines.removeConfirm.title'),
      description: t('purchaseOrderForm.lines.removeConfirm.description', { n: index + 1 }),
      confirmLabel: t('purchaseOrderForm.lines.removeConfirm.confirm'),
    })
    if (confirmed) {
      removeLine(index)
    }
  }

  return (
    <RecordSection
      title={t('purchaseOrderForm.sections.lines')}
      icon={<ListOrdered />}
      full
      action={
        canWrite ? (
          <Button type="button" variant="secondary" size="xs" onClick={startNewLine} disabled={inline.isSaving}>
            <Plus aria-hidden="true" />
            {t('purchaseOrderForm.lines.add')}
          </Button>
        ) : null
      }
    >
      <TooltipProvider>
        <div className="overflow-x-auto">
          <div className="flex flex-col gap-1.5 lg:min-w-[62rem]">
            <PurchaseOrderLinesHeader />
            <ul className="flex flex-col gap-1.5">
              {lineArray.fields.map((field, index) => {
                const values = lines[index]
                const saved = order.lines.find((candidate) => candidate.id === values?.id)
                if (!values) {
                  return null
                }
                if (inline.editingField === lineEditingKey(index) || !saved) {
                  return (
                    <PurchaseOrderLineRow
                      key={field.id}
                      index={index}
                      control={form.control}
                      setValue={form.setValue}
                      line={values}
                      readOnly={false}
                      className={EDITING_LINE_CLASS}
                      onRemove={() => (saved ? void askRemove(index) : inline.cancel())}
                      footer={<LineEditBar inline={inline} />}
                    />
                  )
                }
                return (
                  <PurchaseOrderLineView
                    key={field.id}
                    index={index}
                    line={saved}
                    onEdit={canWrite && !inline.isSaving ? () => inline.start(lineEditingKey(index)) : undefined}
                    onRemove={canWrite && lines.length > 1 ? () => void askRemove(index) : undefined}
                  />
                )
              })}
            </ul>
          </div>
        </div>
      </TooltipProvider>
      <PurchaseOrderTotals totals={previewTotals(lines)} />
    </RecordSection>
  )
}
