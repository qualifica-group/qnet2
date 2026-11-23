import { useCallback, useState } from 'react'
import axios from 'axios'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { refusedActionMessage } from '@/lib/forbidden-message'
import {
  removeDocumentChecklistCategory,
  syncDocumentChecklistCategory,
} from '@/features/document-checklist-categories/api'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const HTTP_UNPROCESSABLE = 422
const DELETE_REFUSAL_FIELD = 'category'

/** Reason of a refused delete: the 422 on `category` (how many checklists block it), else the usual refusal text. */
function deleteRefusalMessage(error: unknown, fallback: string, i18n: Parameters<typeof refusedActionMessage>[2]): string {
  if (axios.isAxiosError(error) && error.response?.status === HTTP_UNPROCESSABLE) {
    const data = error.response.data as { message?: unknown; errors?: Record<string, string[]> } | undefined
    const reason = data?.errors?.[DELETE_REFUSAL_FIELD]?.[0] ?? (typeof data?.message === 'string' ? data.message : '')
    if (reason.trim() !== '') {
      return reason
    }
  }
  return refusedActionMessage(error, fallback, i18n)
}

interface Options {
  openView: (row: TableRow) => void
  refreshGrid: () => void
}

/**
 * Row actions of the categories grid (spec 0410 AC-019). Every success
 * refreshes the SSRM grid; every failure shows the server's specific refusal
 * in a toast (the 422 on `category` says how many checklists block the delete).
 */
export function useDocumentChecklistCategoryRowActions({ openView, refreshGrid }: Options) {
  const { t, i18n } = useTranslation()
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const failure = (fallbackKey: string) => (error: unknown) =>
    toast.error(refusedActionMessage(error, t(fallbackKey), i18n))

  const sync = useMutation({
    mutationFn: (id: number) => syncDocumentChecklistCategory(id),
    onSuccess: () => {
      toast.success(t('documentChecklistCategories.toast.syncAgainQueued'))
      refreshGrid()
    },
    onError: failure('documentChecklistCategories.toast.syncError'),
  })

  const remove = useMutation({
    mutationFn: (id: number) => removeDocumentChecklistCategory(id),
    onSuccess: () => {
      toast.success(t('documentChecklistCategories.form.deleted'))
      refreshGrid()
    },
    onError: (error: unknown) =>
      toast.error(deleteRefusalMessage(error, t('documentChecklistCategories.form.deleteError'), i18n)),
  })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      const id = Number(row.id)
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'sync':
          sync.mutate(id)
          break
        case 'delete':
          remove.mutate(id)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView, sync, remove],
  )

  const isBusy = (row: TableRow) =>
    (sync.isPending && sync.variables === row.id) || (remove.isPending && remove.variables === row.id)

  const resolveActionState = (action: TableActionDefinition): RowActionState | undefined =>
    action.key === 'sync' ? { label: t('documentChecklistCategories.actions.syncAgain') } : undefined

  return {
    handleAction,
    isBusy,
    resolveActionState,
    activityRow,
    closeActivity: () => setActivityRow(null),
  }
}
