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
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { operationalSiteColumnRenderers } from '@/features/operational-sites/column-renderers'
import { deleteOperationalSite } from '@/features/operational-sites/api'

/** Domain key used to mount the generic table for operational sites. */
const OPERATIONAL_SITES_DOMAIN = 'operational-sites'

/**
 * Thin Operational Sites adapter over the generic table. It mounts
 * `<TableView>` with the `operational-sites` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`,
 * resolved from the user's preference (spec 0042). It still owns the delete
 * flow (confirm + toast + grid refresh) and the SSRM grid refresh after
 * every mutation. Permission gating is an affordance only; the backend
 * re-authorizes each call.
 */
export function OperationalSitesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(OPERATIONAL_SITES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(OPERATIONAL_SITES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `OperationalSiteFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(OPERATIONAL_SITES_DOMAIN, {
    onSaved,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteOperationalSite(Number(row.id))
        toast.success(t('operationalSites.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('operationalSites.form.deleteForbidden')
            : t('operationalSites.form.deleteError'),
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
              domain={OPERATIONAL_SITES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="operational-sites.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('operationalSites.form.newOperationalSite')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={OPERATIONAL_SITES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={OPERATIONAL_SITES_DOMAIN}
        renderers={operationalSiteColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={OPERATIONAL_SITES_DOMAIN}
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
