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
import { businessFunctionColumnRenderers } from '@/features/business-functions/column-renderers'
import { deleteBusinessFunction } from '@/features/business-functions/api'

/** Domain key used to mount the generic table for business functions. */
const BUSINESS_FUNCTIONS_DOMAIN = 'business-functions'

/**
 * Thin Business Functions adapter over the generic table. It mounts
 * `<TableView>` with the `business-functions` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal Sheet
 * vs dedicated page) of view/edit/create to `useModuleOpener`, resolved from
 * the user's preference (spec 0042). It still owns the delete flow and refreshes
 * the SSRM grid after every mutation. Permission gating is an affordance only;
 * the backend re-authorizes each call.
 */
export function BusinessFunctionsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(BUSINESS_FUNCTIONS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(BUSINESS_FUNCTIONS_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const onSaved = useCallback(() => {
    refreshGrid()
    invalidateStats()
  }, [refreshGrid, invalidateStats])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(
    BUSINESS_FUNCTIONS_DOMAIN,
    { onSaved },
  )

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteBusinessFunction(Number(row.id))
        toast.success(t('businessFunctions.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('businessFunctions.form.deleteForbidden')
            : t('businessFunctions.form.deleteError'),
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
              domain={BUSINESS_FUNCTIONS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="business-functions.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('businessFunctions.form.newBusinessFunction')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={BUSINESS_FUNCTIONS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={BUSINESS_FUNCTIONS_DOMAIN}
        renderers={businessFunctionColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={BUSINESS_FUNCTIONS_DOMAIN}
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
