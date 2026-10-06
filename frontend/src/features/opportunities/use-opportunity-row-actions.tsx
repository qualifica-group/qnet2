import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import {
  deleteOpportunity,
  OPPORTUNITIES_DOMAIN,
  OPPORTUNITY_ATTACHABLE_ALIAS,
} from '@/features/opportunities/api'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

export interface UseOpportunityRowActionsOptions {
  /** Called after anything that changes the displayed rows: a save, a delete, a documents/notes change. */
  onMutated: () => void
  /** Forces the open mode instead of honoring the user's preference (spec 0067 D-3); omitted, the preference wins. */
  forceMode?: OpenMode
}

export interface UseOpportunityRowActionsResult {
  handleAction: RowActionHandler
  isBusy: (row: TableRow) => boolean
  openCreate: () => void
  /** Opens the create form seeded with `params` (spec 0199: `registry_id` from the anagrafica detail). */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
  /** The activity, documents and notes dialogs the row actions open: the host mounts them once. */
  dialogs: ReactNode
}

/**
 * The Opportunita' action catalog's BEHAVIOR (view/delete/activity/documents/
 * notes), owned once and shared by the Opportunita' grid (`OpportunitiesTable`)
 * and the anagrafica detail's Opportunita' tab (spec 0199), so the two can
 * never drift. The notes dialog opens the `request-management` thread: the
 * notes registry maps the Opportunity record under that entity_type.
 */
export function useOpportunityRowActions({
  onMutated,
  forceMode,
}: UseOpportunityRowActionsOptions): UseOpportunityRowActionsResult {
  const { t } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)
  const [notesRowId, setNotesRowId] = useState<number | null>(null)

  const { openCreate, openCreateWith, openView, sheet } = useModuleOpener(OPPORTUNITIES_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteOpportunity(Number(row.id))
        toast.success(t('opportunities.form.deleted'))
        onMutated()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('opportunities.form.deleteForbidden'))
        } else {
          toast.error(t('opportunities.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [onMutated, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'documents':
          setDocumentsRowId(Number(row.id))
          break
        case 'notes':
          setNotesRowId(Number(row.id))
          break
        default:
          break
      }
    },
    [openView, runDelete],
  )

  // Documents are edited from inside the dialog (upload/delete); refresh the
  // grid on close so the row's `documents_count` badge reflects the change.
  const handleDocumentsOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setDocumentsRowId(null)
        onMutated()
      }
    },
    [onMutated],
  )

  // Notes are added/deleted from inside the dialog: the badge follows each
  // write immediately (`onThreadChanged`), not only the close — chi scrive una
  // nota si aspetta di vedere il conteggio salire subito. La chiusura resta il
  // secondo giro, per le scritture che il dialog non riporta (edit di terzi).
  const handleNotesOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setNotesRowId(null)
        onMutated()
      }
    },
    [onMutated],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const dialogs = (
    <>
      <ResourceActivityDialog
        resource={OPPORTUNITIES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />

      <DocumentsDialog
        resource={OPPORTUNITY_ATTACHABLE_ALIAS}
        id={documentsRowId}
        onOpenChange={handleDocumentsOpenChange}
      />

      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesRowId}
        onThreadChanged={onMutated}
        onOpenChange={handleNotesOpenChange}
      />
    </>
  )

  return { handleAction, isBusy, openCreate, openCreateWith, sheet, dialogs }
}
