import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ApiErrorResponse } from '@/api/types'
import { rewardTypeColumnRenderers } from '@/features/reward-types/column-renderers'
import { deleteRewardType } from '@/features/reward-types/api'

/** Domain key used to mount the generic table for reward types. */
const REWARD_TYPES_DOMAIN = 'reward-types'

/**
 * Thin Reward Types adapter over the generic table. It mounts `<TableView>`
 * with the `reward-types` domain, its custom cell renderers and a row-action
 * handler, and delegates the open mode (modal Sheet vs dedicated page) of
 * view/edit/create to `useModuleOpener`, resolved from the user's preference
 * (spec 0042). It still owns the delete flow (confirming via the generic
 * `useConfirm` dialog wired into the row action, running the delete mutation
 * and surfacing the outcome toast — including the 409 branch, unreachable
 * today per BR-3 but a zero-cost point of extension) and refreshes the SSRM
 * grid after every mutation via the table's imperative handle. Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function RewardTypesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(REWARD_TYPES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteRewardType(row.id)
        toast.success(t('rewardTypes.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('rewardTypes.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('rewardTypes.form.deleteForbidden'))
        } else if (status === 409) {
          // BR-3: no delete-guard exists yet, but the branch is prewired so a
          // future FK reference degrades to a toast, not a silent failure.
          toast.error(error.response?.data?.message ?? t('rewardTypes.form.deleteInUseFallback'))
        } else {
          toast.error(t('rewardTypes.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
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
        default:
          break
      }
    },
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="reward-types.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('rewardTypes.form.newRewardType')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={REWARD_TYPES_DOMAIN}
        renderers={rewardTypeColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={REWARD_TYPES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
