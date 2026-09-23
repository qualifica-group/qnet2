import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { taskColumnRenderers } from '@/features/tasks/task-column-renderers'
import { useTaskListUrlFilters } from '@/features/tasks/use-task-list-url-filters'
import { useTaskRowActions } from '@/features/tasks/use-task-row-actions'

/**
 * Thin `tasks` adapter over the generic table (AC-070): search, sorting,
 * server paging, column selection, saved preferences and export all come from
 * the generic engine — no dedicated endpoint, the work-order board's filters
 * included (spec 0147, backend advanced filters). Row behaviour is delegated to
 * `useTaskRowActions`; a mutation also refreshes the statistics panel. Permission gating here is an affordance only; the
 * backend re-authorizes each call and additionally restricts the rows by the
 * visibility scope (D-9).
 */
export function TasksTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const stats = useStatsPanel(TASKS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(TASKS_DOMAIN)
  const urlFilters = useTaskListUrlFilters()
  const handleMutated = useCallback(() => {
    tableRef.current?.refresh()
    invalidateStats()
  }, [invalidateStats])

  const { handleAction, isBusy, activityRow, closeActivity, openCreate, sheet } = useTaskRowActions({
    onMutated: handleMutated,
  })

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton domain={TASKS_DOMAIN} isOpen={stats.isOpen} onToggle={stats.toggle} />
            <Can permission="tasks.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('tasks.form.newTask')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={TASKS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={TASKS_DOMAIN}
        renderers={taskColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        advancedFiltersOverride={urlFilters.override}
        onAdvancedFiltersOverrideCleared={urlFilters.clear}
      />

      {sheet}

      <ResourceActivityDialog resource={TASKS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </div>
  )
}
