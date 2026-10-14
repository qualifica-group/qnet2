import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ListOrdered, Loader2, Plus, X } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Button } from '@/components/ui/button'
import { TooltipProvider } from '@/components/ui/tooltip'
import { LineStatusDialog } from '@/features/purchase-requests/line-status-dialog'
import { lineToTarget, type LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { previewTotals } from '@/features/purchase-requests/purchase-request-amounts'
import { PurchaseRequestLineRow } from '@/features/purchase-requests/purchase-request-line-row'
import { PurchaseRequestLineView } from '@/features/purchase-requests/purchase-request-line-view'
import { PurchaseRequestLinesHeader } from '@/features/purchase-requests/purchase-request-lines-header'
import { PurchaseRequestTotals } from '@/features/purchase-requests/purchase-request-totals'
import type { PurchaseRequest } from '@/features/purchase-requests/types'
import {
  lineEditingKey,
  type PurchaseRequestDetailEditor,
} from '@/features/purchase-requests/use-purchase-request-inline-edit'

const NO_TARGETS: readonly LineTarget[] = []

/** The open line reads as "being edited", like an open field row. */
const EDITING_LINE_CLASS = 'border-ring/50 ring-[3px] ring-ring/15'

interface PurchaseRequestLinesRecordProps {
  request: PurchaseRequest
  editor: PurchaseRequestDetailEditor
}

interface LineEditBarProps {
  editor: PurchaseRequestDetailEditor
}

/** Annulla/Salva under the open line, with the refusal no field message can carry. */
function LineEditBar({ editor }: LineEditBarProps) {
  const { inline } = editor
  return (
    <div className="flex flex-col gap-1.5 pt-1">
      {inline.error ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {inline.error}
        </p>
      ) : null}
      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="ghost" size="xs" onClick={inline.cancel} disabled={inline.isSaving}>
          <X aria-hidden="true" />
          {inline.cancelLabel}
        </Button>
        <Button type="button" size="xs" onClick={inline.save} disabled={inline.isSaving}>
          {inline.isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Check aria-hidden="true" />}
          {inline.confirmLabel}
        </Button>
      </div>
    </div>
  )
}

/**
 * The RDA lines on the detail (spec 0256 D-7): each saved line is a read-only
 * band; its pencil opens the editable row in its place, and Salva sends that
 * line alone (the other lines travel unchanged). "Aggiungi riga" opens a new
 * line the same way; the trash asks and deletes right away. Status change,
 * documents and reason stay on every line.
 */
export function PurchaseRequestLinesRecord({ request, editor }: PurchaseRequestLinesRecordProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { form, lineArray, lines, inline, startNewLine, removeLine } = editor
  const [targets, setTargets] = useState<readonly LineTarget[]>(NO_TARGETS)
  const isOpen = request.status === 'open'
  const canWrite = isOpen && request.abilities.update

  const askRemove = async (index: number) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('purchaseRequests.lines.removeConfirm.title'),
      description: t('purchaseRequests.lines.removeConfirm.description', { n: index + 1 }),
      confirmLabel: t('purchaseRequests.lines.removeConfirm.confirm'),
    })
    if (confirmed) {
      removeLine(index)
    }
  }

  return (
    <RecordSection
      title={t('purchaseRequests.sections.lines')}
      icon={<ListOrdered />}
      full
      action={
        canWrite ? (
          <Button type="button" variant="secondary" size="xs" onClick={startNewLine} disabled={inline.isSaving}>
            <Plus aria-hidden="true" />
            {t('purchaseRequests.lines.add')}
          </Button>
        ) : null
      }
    >
      <TooltipProvider>
        <div className="overflow-x-auto">
          <div className="flex flex-col gap-1.5 lg:min-w-[62rem]">
            <PurchaseRequestLinesHeader />
            <ul className="flex flex-col gap-1.5">
              {lineArray.fields.map((field, index) => {
                const values = lines[index]
                const saved = request.lines.find((candidate) => candidate.id === values?.id)
                if (!values) {
                  return null
                }
                if (inline.editingField === lineEditingKey(index) || !saved) {
                  return (
                    <PurchaseRequestLineRow
                      key={field.id}
                      index={index}
                      control={form.control}
                      setValue={form.setValue}
                      line={values}
                      className={EDITING_LINE_CLASS}
                      onRemove={() => (saved ? void askRemove(index) : inline.cancel())}
                      onChangeStatus={() => (saved ? setTargets([lineToTarget(saved, request, values.transitions)]) : undefined)}
                      footer={<LineEditBar editor={editor} />}
                    />
                  )
                }
                const editable = canWrite && (!values.locked || values.can_update_supplier)
                const transitions = isOpen ? saved.abilities.transitions : []
                return (
                  <PurchaseRequestLineView
                    key={field.id}
                    index={index}
                    control={form.control}
                    line={saved}
                    onEdit={editable && !inline.isSaving ? () => inline.start(lineEditingKey(index)) : undefined}
                    onRemove={canWrite && values.can_delete ? () => void askRemove(index) : undefined}
                    onChangeStatus={
                      transitions.length > 0 ? () => setTargets([lineToTarget(saved, request, transitions)]) : undefined
                    }
                  />
                )
              })}
            </ul>
          </div>
        </div>
      </TooltipProvider>
      <PurchaseRequestTotals totals={previewTotals(lines)} />
      <LineStatusDialog
        key={targets.map((target) => target.id).join(',')}
        targets={targets}
        onOpenChange={(open) => (open ? undefined : setTargets(NO_TARGETS))}
        onChanged={() => setTargets(NO_TARGETS)}
      />
    </RecordSection>
  )
}
