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
import { companyColumnRenderers } from '@/features/companies/column-renderers'
import { deleteCompany } from '@/features/companies/api'

/** Domain key used to mount the generic table for companies. */
const COMPANIES_DOMAIN = 'companies'

/**
 * Thin Companies adapter over the generic table. It mounts `<TableView>` with
 * the `companies` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (modal Sheet vs dedicated page) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec
 * 0042). It still owns the delete flow (confirm + toast + grid refresh) and
 * the SSRM grid refresh after every mutation. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function CompaniesTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(COMPANIES_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(COMPANIES_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid and
  // the stats panel are this adapter's to refresh. The detail query is
  // invalidated inside `CompanyFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(COMPANIES_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteCompany(Number(row.id))
        toast.success(t('companies.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('companies.form.deleteForbidden')
            : t('companies.form.deleteError'),
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

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId,
    [deletingId],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={COMPANIES_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="companies.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('companies.form.newCompany')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={COMPANIES_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={COMPANIES_DOMAIN}
        renderers={companyColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={COMPANIES_DOMAIN}
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
