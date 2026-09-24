import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { deleteTask, TASKS_DOMAIN } from '@/features/tasks/api'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

export interface UseTaskRowActionsOptions {
  /** Called after anything that changes the displayed rows: a create, an update or a delete. */
  onMutated: () => void
  /** Forces the open mode instead of honoring the user's preference (spec 0067 D-3); omitted, the preference wins. */
  forceMode?: OpenMode
}

export interface UseTaskRowActionsResult {
  handleAction: RowActionHandler
  isBusy: (row: TableRow) => boolean
  activityRow: TableRow | null
  closeActivity: (open: boolean) => void
  /** The row currently showing its notes panel (spec 0156 D-5), `null` when none. */
  notesRowId: number | null
  closeNotes: (open: boolean) => void
  openCreate: () => void
  /** Opens the create form seeded with `params` (spec 0133 D-4: `work_order_id` from the Commessa detail). */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
}

/**
 * The Task action catalog's BEHAVIOR (create/view/edit/delete/activity),
 * owned once so no surface rendering those actions can drift.
 *
 * The 409 branch is the sub-task guard (D-8a/AC-015): a task with children is
 * never deleted, and the user is told why rather than shown a generic error.
 */
export function useTaskRowActions({
  onMutated,
  forceMode,
}: UseTaskRowActionsOptions): UseTaskRowActionsResult {
  const { t } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [notesRowId, setNotesRowId] = useState<number | null>(null)

  const { openCreate, openCreateWith, openView, openEdit, openDuplicate, sheet } = useModuleOpener(TASKS_DOMAIN, {
    onSaved: onMutated,
    forceMode,
    viewAfterCreate: true,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteTask(Number(row.id))
        toast.success(t('tasks.form.deleted'))
        onMutated()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('tasks.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('tasks.form.deleteConflict'))
        } else {
          toast.error(t('tasks.form.deleteError'))
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
        case 'edit':
          openEdit(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'duplicate':
          openDuplicate(row)
          break
        case 'notes':
          setNotesRowId(Number(row.id))
          break
        default:
          break
      }
    },
    [openView, openEdit, openDuplicate, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const closeActivity = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  const closeNotes = useCallback((open: boolean) => {
    if (!open) {
      setNotesRowId(null)
    }
  }, [])

  return {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    notesRowId,
    closeNotes,
    openCreate,
    openCreateWith,
    sheet,
  }
}
