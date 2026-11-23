import { useCallback, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { refusedActionMessage } from '@/lib/forbidden-message'
import {
  removeDocumentChecklist,
  setDocumentChecklistActivation,
  syncAllDocumentChecklists,
  syncDocumentChecklist,
} from '@/features/document-checklists/api'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

interface Options {
  openView: (row: TableRow) => void
  refreshGrid: () => void
}

/**
 * Row actions and toolbar mutations of the document checklists grid (spec
 * 0403, AC-025). Every success refreshes the SSRM grid; every failure surfaces
 * the server's specific refusal, falling back to a feature message.
 */
export function useDocumentChecklistRowActions({ openView, refreshGrid }: Options) {
  const { t, i18n } = useTranslation()
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const failure = (fallbackKey: string) => (error: unknown) =>
    toast.error(refusedActionMessage(error, t(fallbackKey), i18n))

  const sync = useMutation({
    mutationFn: (id: number) => syncDocumentChecklist(id),
    onSuccess: () => {
      toast.success(t('documentChecklists.toast.syncAgainQueued'))
      refreshGrid()
    },
    onError: failure('documentChecklists.toast.syncError'),
  })

  const syncAll = useMutation({
    mutationFn: () => syncAllDocumentChecklists(),
    onSuccess: ({ queued }) => {
      toast.success(t('documentChecklists.toast.syncQueued', { count: queued }))
      refreshGrid()
    },
    onError: failure('documentChecklists.toast.syncError'),
  })

  const activation = useMutation({
    mutationFn: ({ id, isActive }: { id: number; isActive: boolean }) => setDocumentChecklistActivation(id, isActive),
    onSuccess: (checklist) => {
      toast.success(t(checklist.is_active ? 'documentChecklists.toast.activated' : 'documentChecklists.toast.deactivated'))
      refreshGrid()
    },
    onError: failure('documentChecklists.toast.activationError'),
  })

  const remove = useMutation({
    mutationFn: (id: number) => removeDocumentChecklist(id),
    onSuccess: () => {
      toast.success(t('documentChecklists.form.deleted'))
      refreshGrid()
    },
    onError: failure('documentChecklists.form.deleteError'),
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
        case 'activate':
          activation.mutate({ id, isActive: row.is_active !== true })
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
    [openView, sync, activation, remove],
  )

  const isBusy = (row: TableRow) =>
    (sync.isPending && sync.variables === row.id) ||
    (activation.isPending && activation.variables?.id === row.id) ||
    (remove.isPending && remove.variables === row.id)

  // The backend catalogues a single `activate` action: its label follows the row state.
  const resolveActionState = (action: TableActionDefinition, row: TableRow): RowActionState | undefined => {
    if (action.key === 'sync') {
      return { label: t('documentChecklists.actions.syncAgain') }
    }
    if (action.key === 'activate') {
      return { label: t(row.is_active === true ? 'documentChecklists.actions.deactivate' : 'documentChecklists.actions.activate') }
    }
    return undefined
  }

  return {
    handleAction,
    isBusy,
    resolveActionState,
    syncAll: () => syncAll.mutate(),
    isSyncingAll: syncAll.isPending,
    activityRow,
    closeActivity: () => setActivityRow(null),
  }
}
