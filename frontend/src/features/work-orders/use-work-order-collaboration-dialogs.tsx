import { useState, type ReactNode } from 'react'
import { MessagesSquare, Paperclip } from 'lucide-react'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { NotesDialog } from '@/features/notes/notes-dialog'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { WORK_ORDER_ATTACHABLE_ALIAS, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'

/** Icons of the `documents`/`notes` row actions, common to the commercial grids (spec 0363). */
export const COLLABORATION_ACTION_ICONS: ActionIconMap = { paperclip: Paperclip, 'messages-square': MessagesSquare }

interface WorkOrderCollaborationDialogs {
  openDocuments: (workOrderId: number) => void
  openNotes: (workOrderId: number) => void
  /** Both dialogs, for the host to mount. */
  dialogs: ReactNode
}

/**
 * The documents and notes dialogs of a Commessa row (spec 0363), the same
 * surfaces as the detail tabs. Both are written from inside the dialog, so
 * closing one refreshes the host (the row badges).
 */
export function useWorkOrderCollaborationDialogs(onMutated: () => void): WorkOrderCollaborationDialogs {
  const [documentsId, setDocumentsId] = useState<number | null>(null)
  const [notesId, setNotesId] = useState<number | null>(null)

  const dialogs = (
    <>
      <DocumentsDialog
        resource={WORK_ORDER_ATTACHABLE_ALIAS}
        id={documentsId}
        onOpenChange={(open) => {
          if (!open) {
            setDocumentsId(null)
            onMutated()
          }
        }}
      />
      <NotesDialog
        entityType={WORK_ORDERS_DOMAIN}
        entityId={notesId}
        onThreadChanged={onMutated}
        onOpenChange={(open) => {
          if (!open) {
            setNotesId(null)
            onMutated()
          }
        }}
      />
    </>
  )

  return { openDocuments: setDocumentsId, openNotes: setNotesId, dialogs }
}
