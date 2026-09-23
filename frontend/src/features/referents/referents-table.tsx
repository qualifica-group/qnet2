import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { referentColumnRenderers } from '@/features/referents/column-renderers'
import { deleteReferent } from '@/features/referents/api'

/** Domain key used to mount the generic table for referents. */
const REFERENTS_DOMAIN = 'referents'

/**
 * Thin Referents adapter over the generic table. It mounts `<TableView>` with
 * the `referents` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (dedicated page vs modal Sheet) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec
 * 0042). Its default stays the bespoke dedicated pages (spec 0022); the
 * generic table still owns the delete flow (confirm + toast + grid refresh)
 * and the SSRM grid refresh after every mutation via the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function ReferentsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(REFERENTS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(REFERENTS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `ReferentFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(REFERENTS_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteReferent(Number(row.id))
        toast.success(t('referents.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('referents.form.deleteForbidden') : t('referents.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
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
          <>
            <StatsToggleButton
              domain={REFERENTS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="referents.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('referents.form.newReferent')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={REFERENTS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={REFERENTS_DOMAIN}
        renderers={referentColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={REFERENTS_DOMAIN}
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
