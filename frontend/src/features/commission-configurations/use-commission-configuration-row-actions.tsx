import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { COMMISSION_CONFIGURATIONS_DOMAIN, deleteCommissionConfiguration } from './api'

interface Options {
  /** Called after anything that changes the displayed rows: a save or a delete. */
  onMutated: () => void
  /** Forces the open mode instead of honoring the user's preference (spec 0067 D-3). */
  forceMode?: OpenMode
}

interface Result {
  handleAction: RowActionHandler
  isBusy: (row: TableRow) => boolean
  openCreate: () => void
  /** Opens the create form seeded with `params` (spec 0204: the supplier as recipient). */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
  /** The activity dialog the row actions open: the host mounts it once. */
  dialogs: ReactNode
}

/**
 * The Configuratore commissioni's row actions (view/delete/activity), owned
 * once and shared by its grid and the anagrafica detail's "Commissioni
 * configurate" tab (spec 0204), so the two can never drift.
 */
export function useCommissionConfigurationRowActions({ onMutated, forceMode }: Options): Result {
  const { t } = useTranslation()
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const { openCreate, openCreateWith, openView, sheet } = useModuleOpener(COMMISSION_CONFIGURATIONS_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })

  const remove = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteCommissionConfiguration(Number(row.id))
        toast.success(t('commissionConfigurations.form.deleted'))
        onMutated()
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 409) {
          toast.error(error.response.data?.message ?? t('commissionConfigurations.form.deleteReferenced'))
        } else {
          toast.error(t('commissionConfigurations.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [onMutated, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') openView(row)
      if (action.key === 'delete') void remove(row)
      if (action.key === 'activity') setActivityRow(row)
    },
    [openView, remove],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const dialogs = (
    <ResourceActivityDialog
      resource={COMMISSION_CONFIGURATIONS_DOMAIN}
      row={activityRow}
      onOpenChange={(open) => {
        if (!open) setActivityRow(null)
      }}
    />
  )

  return { handleAction, isBusy, openCreate, openCreateWith, sheet, dialogs }
}
