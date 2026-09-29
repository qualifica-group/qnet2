import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { formatBytes } from '@/features/attachments/format-bytes'
import type { ComposeContextDocument, ComposeContextDocumentSource } from '@/features/work-order-emails/types'

/** Group order: the commessa's own documents lead, the anagrafica's follow (D-7b). */
const GROUP_ORDER: ComposeContextDocumentSource[] = ['work_order', 'registry']

interface DocumentGroup {
  source: ComposeContextDocumentSource
  items: ComposeContextDocument[]
}

function groupBySource(documents: ComposeContextDocument[]): DocumentGroup[] {
  return GROUP_ORDER.map((source) => ({ source, items: documents.filter((doc) => doc.source === source) })).filter(
    (group) => group.items.length > 0,
  )
}

export interface WorkOrderEmailDocumentsPickerDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  documents: ComposeContextDocument[]
  onImport: (attachmentIds: number[]) => Promise<void>
  isImporting: boolean
}

/**
 * "Da documenti" (D-7b, AC-021): checklist of the commessa's own and its
 * anagrafica's `documents`, grouped by source. Selection resets every time
 * the dialog reopens — it never carries a stale pick across sessions.
 */
export function WorkOrderEmailDocumentsPickerDialog({
  open,
  onOpenChange,
  documents,
  onImport,
  isImporting,
}: WorkOrderEmailDocumentsPickerDialogProps) {
  const { t } = useTranslation()
  const [selected, setSelected] = useState<number[]>([])
  const groups = useMemo(() => groupBySource(documents), [documents])

  // Reset happens on CLOSE (not on open via an effect, which would cascade a
  // render): the selection is already clean the next time this dialog opens.
  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setSelected([])
    }
    onOpenChange(next)
  }

  const toggle = (id: number) => {
    setSelected((prev) => (prev.includes(id) ? prev.filter((current) => current !== id) : [...prev, id]))
  }

  const handleConfirm = async () => {
    if (selected.length === 0) {
      return
    }
    await onImport(selected)
    handleOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle>{t('workOrderEmails.composer.attachments.documentsDialogTitle')}</DialogTitle>
        </DialogHeader>

        {documents.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('workOrderEmails.composer.attachments.documentsEmpty')}</p>
        ) : (
          <div className="flex max-h-[50vh] flex-col gap-4 overflow-y-auto">
            {groups.map((group) => (
              <div key={group.source} className="flex flex-col gap-1.5">
                <p className="text-xs font-semibold text-muted-foreground">
                  {t(`workOrderEmails.composer.attachments.documentsGroup.${group.source}`)}
                </p>
                <ul className="flex flex-col gap-1">
                  {group.items.map((doc) => (
                    <li key={doc.id}>
                      <label className="flex cursor-pointer items-center gap-2 rounded-md p-1.5 text-sm hover:bg-accent/40">
                        <Checkbox checked={selected.includes(doc.id)} onCheckedChange={() => toggle(doc.id)} />
                        <span className="min-w-0 flex-1 truncate">{doc.original_name}</span>
                        <span className="shrink-0 text-xs text-muted-foreground">{formatBytes(doc.size)}</span>
                      </label>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
            {t('workOrderEmails.composer.cancel')}
          </Button>
          <Button type="button" onClick={() => void handleConfirm()} disabled={selected.length === 0 || isImporting}>
            {t('workOrderEmails.composer.attachments.importConfirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
