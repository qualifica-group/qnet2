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
import { NotesDialog } from '@/features/notes/notes-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { taskColumnRenderers } from '@/features/tasks/task-column-renderers'
import { TaskQuickCreateRow } from '@/features/tasks/task-quick-create-row'
import { useTaskBulkActionsSlot } from '@/features/tasks/use-task-bulk-actions-slot'
import { useTaskDomainRowActions } from '@/features/tasks/use-task-domain-row-actions'
import { useTaskListUrlFilters } from '@/features/tasks/use-task-list-url-filters'
import { useTaskRowActions } from '@/features/tasks/use-task-row-actions'
import { useTaskStatusCellIntercept } from '@/features/tasks/use-task-status-cell-intercept'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRowsAggregates } from '@/features/table/types'

/**
 * Thin `tasks` adapter over the generic table (AC-070, extended by spec
 * 0156): search, sorting, server paging, column selection, saved preferences
 * and export all come from the generic engine — no dedicated endpoint. Row
 * behaviour is split across three focused hooks (CRUD-ish actions, the six
 * domain-workflow actions, and the bulk-actions dropdown) so this component
 * stays a thin orchestrator; a mutation also refreshes the statistics panel.
 * Permission gating here is an affordance only; the backend re-authorizes
 * each call and additionally restricts the rows by the visibility scope
 * (D-9).
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
  const clearSelection = useCallback(() => tableRef.current?.clearSelection(), [])

  const {
    handleAction: handleCrudAction,
    isBusy,
    activityRow,
    closeActivity,
    notesRowId,
    closeNotes,
    openCreate,
    sheet,
  } = useTaskRowActions({ onMutated: handleMutated })

  const { handleAction: handleDomainAction, dialogSlot: domainDialogSlot } = useTaskDomainRowActions({
    onMutated: handleMutated,
  })

  // Neither hook's own action key set overlaps the other's (each `switch`
  // falls through to a no-op `default` for a key it does not own), so both
  // can dispatch every row action unconditionally.
  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      handleCrudAction(action, row)
      handleDomainAction(action, row)
    },
    [handleCrudAction, handleDomainAction],
  )

  const { getBulkActions, dialogSlot: bulkDialogSlot } = useTaskBulkActionsSlot({
    refresh: handleMutated,
    clearSelection,
  })

  const { interceptCommit, dialogSlot: statusDialogSlot } = useTaskStatusCellIntercept({
    onMutated: handleMutated,
  })

  const renderFooter = useCallback(
    (aggregates: TableRowsAggregates | undefined) =>
      aggregates ? (
        <span className="text-xs text-muted-foreground">
          {t('tasks.footer.estimatedMinutesTotal', {
            value: formatMinutesLabel(aggregates.estimated_minutes_total ?? 0),
          })}
        </span>
      ) : null,
    [t],
  )

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
        getBulkActions={getBulkActions}
        disableBuiltinDelete
        renderFooter={renderFooter}
        pinnedRowSlot={<TaskQuickCreateRow onCreated={handleMutated} />}
        interceptCellCommit={interceptCommit}
      />

      {sheet}
      {domainDialogSlot}
      {bulkDialogSlot}
      {statusDialogSlot}

      <ResourceActivityDialog resource={TASKS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
      <NotesDialog entityType={TASKS_DOMAIN} entityId={notesRowId} onThreadChanged={handleMutated} onOpenChange={closeNotes} />
    </div>
  )
}
