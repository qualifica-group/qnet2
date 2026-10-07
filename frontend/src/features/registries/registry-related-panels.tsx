import { useCallback, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { COMMISSION_CONFIGURATIONS_DOMAIN } from '@/features/commission-configurations/api'
import { commissionConfigurationColumnRenderers } from '@/features/commission-configurations/column-renderers'
import { COMMISSION_CREATE_REGISTRY_PARAM } from '@/features/commission-configurations/types'
import { useCommissionConfigurationRowActions } from '@/features/commission-configurations/use-commission-configuration-row-actions'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { OPPORTUNITIES_ACTION_ICONS } from '@/features/opportunities/action-icons'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import { opportunityColumnRenderers } from '@/features/opportunities/column-renderers'
import { useOpportunityRowActions } from '@/features/opportunities/use-opportunity-row-actions'
import { QUOTES_ACTION_ICONS } from '@/features/quotes/action-icons'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { QUOTE_CREATE_REGISTRY_PARAM } from '@/features/quotes/quote-create-params'
import { useQuoteRowActions } from '@/features/quotes/use-quote-row-actions'
import { RegistryRelatedGrid } from '@/features/registries/registry-related-grid'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { TASK_ACTION_ICONS } from '@/features/tasks/action-icons'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { TaskCompleteRowContext } from '@/features/tasks/task-complete-row-context'
import { taskColumnRenderers } from '@/features/tasks/task-column-renderers'
import { useTaskDomainRowActions } from '@/features/tasks/use-task-domain-row-actions'
import { useTaskRowActions } from '@/features/tasks/use-task-row-actions'
import { useTaskStatusCellIntercept } from '@/features/tasks/use-task-status-cell-intercept'
import { workOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { WORK_ORDER_ACTION_ICONS } from '@/features/work-orders/use-work-order-closure'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableViewHandle } from '@/features/table/table-view'

/** The create param every module reads as "this anagrafica" (spec 0199 D-6). */
const REGISTRY_CREATE_PARAM = 'registry_id'

/** The Task grid's `assignment` advanced filter and the two values that drop its "assigned to me" default. */
const TASK_ASSIGNMENT_FILTER = 'assignment'
const TASK_ASSIGNMENT_VISIBLE = 'visible'
const TASK_ASSIGNMENT_ALL_ROLES = 'all'

export interface RegistryRelatedPanelProps {
  registryId: number
  onRowCountChanged: (count: number | null) => void
}

/** The grid handle plus its refresh, the `onMutated` every row-actions hook takes. */
function useRelatedGridRefresh() {
  const tableRef = useRef<TableViewHandle>(null)
  const refresh = useCallback(() => tableRef.current?.refresh(), [])
  return { tableRef, refresh }
}

/*
 * One panel per module, each on the module's OWN row-actions hook — the same
 * behavior its list page has — forced into the Sheet so the anagrafica is
 * never abandoned (spec 0067 D-3), and the module's create opened with the
 * anagrafica as create param (D-6). Mounted only inside its own tab, which is
 * itself gated by the module's `viewAny`.
 */

export function RegistryOpportunitiesPanel({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { tableRef, refresh } = useRelatedGridRefresh()
  const { handleAction, isBusy, openCreateWith, sheet, dialogs } = useOpportunityRowActions({
    onMutated: refresh,
    forceMode: OPEN_MODE_MODAL,
  })

  return (
    <>
      <RegistryRelatedGrid
        ref={tableRef}
        domain={OPPORTUNITIES_DOMAIN}
        registryId={registryId}
        renderers={opportunityColumnRenderers}
        iconMap={OPPORTUNITIES_ACTION_ICONS}
        onAction={handleAction}
        isBusy={isBusy}
        onRowCountChanged={onRowCountChanged}
        create={
          can('opportunities.create')
            ? {
                label: t('opportunities.form.newOpportunity'),
                onCreate: () => openCreateWith({ [REGISTRY_CREATE_PARAM]: registryId }),
              }
            : null
        }
      />
      {sheet}
      {dialogs}
    </>
  )
}

export function RegistryQuotesPanel({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { tableRef, refresh } = useRelatedGridRefresh()
  const { handleAction, isBusy, activityRow, closeActivity, notesTarget, closeNotes, openCreateWith, sheet } =
    useQuoteRowActions({ onMutated: refresh, forceMode: OPEN_MODE_MODAL })

  return (
    <>
      <RegistryRelatedGrid
        ref={tableRef}
        domain={QUOTES_DOMAIN}
        registryId={registryId}
        renderers={quoteColumnRenderers}
        iconMap={QUOTES_ACTION_ICONS}
        onAction={handleAction}
        isBusy={isBusy}
        onRowCountChanged={onRowCountChanged}
        create={
          can('quotes.create')
            ? {
                label: t('quotes.form.newQuote'),
                onCreate: () => openCreateWith({ [QUOTE_CREATE_REGISTRY_PARAM]: registryId }),
              }
            : null
        }
      />
      {sheet}
      <ResourceActivityDialog resource={QUOTES_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
      {/* Spec 0085: an Offerta's note lives on its parent Opportunita' thread, filtered on that Offerta. */}
      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesTarget?.opportunityId ?? null}
        lockedQuoteId={notesTarget?.quoteId ?? null}
        title={notesTarget?.code}
        onOpenChange={closeNotes}
        onThreadChanged={refresh}
      />
    </>
  )
}

export function RegistryWorkOrdersPanel({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { tableRef, refresh } = useRelatedGridRefresh()
  const { handleAction, isBusy, activityRow, closeActivity, openCreateWith, sheet, forceCloseDialog } =
    useWorkOrderRowActions({ onMutated: refresh, forceMode: OPEN_MODE_MODAL })

  return (
    <>
      <RegistryRelatedGrid
        ref={tableRef}
        domain={WORK_ORDERS_DOMAIN}
        registryId={registryId}
        renderers={workOrderColumnRenderers}
        iconMap={WORK_ORDER_ACTION_ICONS}
        onAction={handleAction}
        isBusy={isBusy}
        onRowCountChanged={onRowCountChanged}
        create={
          can('work-orders.create')
            ? {
                label: t('workOrders.form.newWorkOrder'),
                onCreate: () => openCreateWith({ [REGISTRY_CREATE_PARAM]: registryId }),
              }
            : null
        }
      />
      {sheet}
      {forceCloseDialog}
      <ResourceActivityDialog resource={WORK_ORDERS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </>
  )
}

export function RegistryTasksPanel({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { tableRef, refresh } = useRelatedGridRefresh()
  const {
    handleAction: handleCrudAction,
    isBusy,
    activityRow,
    closeActivity,
    notesRowId,
    closeNotes,
    openCreateWith,
    sheet,
  } = useTaskRowActions({ onMutated: refresh, forceMode: OPEN_MODE_MODAL })
  const { handleAction: handleDomainAction, completeRow, dialogSlot } = useTaskDomainRowActions({ onMutated: refresh })
  // The status cell edits inline here too: closing a task through it must open
  // the same completion dialog the Task page does.
  const { interceptCommit, dialogSlot: statusDialogSlot } = useTaskStatusCellIntercept({ onMutated: refresh })
  // The Task grid opens on "assigned to me": here the client's tasks are the
  // point, so this visit starts on every task the actor may see — `visible`
  // with viewAll/viewSite, otherwise `all` (every role, the same set for them).
  const canSeeBeyondRoles = can('tasks.viewAll') || can('tasks.viewSite')
  const assignmentOverride = useMemo(
    () => ({ [TASK_ASSIGNMENT_FILTER]: [canSeeBeyondRoles ? TASK_ASSIGNMENT_VISIBLE : TASK_ASSIGNMENT_ALL_ROLES] }),
    [canSeeBeyondRoles],
  )

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      handleCrudAction(action, row)
      handleDomainAction(action, row)
    },
    [handleCrudAction, handleDomainAction],
  )

  return (
    <TaskCompleteRowContext.Provider value={completeRow}>
      <RegistryRelatedGrid
        ref={tableRef}
        domain={TASKS_DOMAIN}
        registryId={registryId}
        renderers={taskColumnRenderers}
        iconMap={TASK_ACTION_ICONS}
        onAction={handleAction}
        isBusy={isBusy}
        onRowCountChanged={onRowCountChanged}
        interceptCellCommit={interceptCommit}
        advancedFiltersOverride={assignmentOverride}
        create={
          can('tasks.create')
            ? {
                label: t('tasks.form.newTask'),
                onCreate: () => openCreateWith({ [REGISTRY_CREATE_PARAM]: registryId }),
              }
            : null
        }
      />
      {sheet}
      {dialogSlot}
      {statusDialogSlot}
      <ResourceActivityDialog resource={TASKS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
      <NotesDialog entityType={TASKS_DOMAIN} entityId={notesRowId} onThreadChanged={refresh} onOpenChange={closeNotes} />
    </TaskCompleteRowContext.Provider>
  )
}

/** Spec 0204: the Configuratore commissioni rules whose recipient is this supplier. */
export function RegistryCommissionConfigurationsPanel({ registryId, onRowCountChanged }: RegistryRelatedPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { tableRef, refresh } = useRelatedGridRefresh()
  const { handleAction, isBusy, openCreateWith, sheet, dialogs } = useCommissionConfigurationRowActions({
    onMutated: refresh,
    forceMode: OPEN_MODE_MODAL,
  })

  return (
    <>
      <RegistryRelatedGrid
        ref={tableRef}
        domain={COMMISSION_CONFIGURATIONS_DOMAIN}
        registryId={registryId}
        renderers={commissionConfigurationColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        onRowCountChanged={onRowCountChanged}
        create={
          can('commission-configurations.create')
            ? {
                label: t('commissionConfigurations.form.new'),
                onCreate: () => openCreateWith({ [COMMISSION_CREATE_REGISTRY_PARAM]: registryId }),
              }
            : null
        }
      />
      {sheet}
      {dialogs}
    </>
  )
}
