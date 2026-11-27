import { useCallback, useRef, type ReactNode } from 'react'
import { PageHeader } from '@/components/page-header'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE, type TableConfigScope } from '@/features/table/use-table-config'
import type { TableRowScope } from '@/features/table/types'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import { useWorkPhaseTabs } from '@/features/work-orders/use-work-phase-tabs'
import { WorkPhaseTabs } from '@/features/work-orders/work-phase-tabs'

/**
 * "Commesse" lists the `processing` work orders only: the project ones are in "Commesse di
 * Progetto", a module of its own (user decision 2026-11-25, back to spec 0359 D-2).
 */
const PROCESSING_ROW_SCOPE: TableRowScope = { workOrderType: 'processing' }

interface WorkOrdersGridProps {
  /** Grid domain: `work-orders` or the project-only `project-work-orders` (same columns and actions). */
  domain: string
  rowScope?: TableRowScope
  /** Config scope of the grid; only the "Commesse" page takes the filter-driven flexible-field columns (spec 0374). */
  scope?: TableConfigScope
  /** Shows the working-phase tabs at the top right; only the "Commesse" page has them. */
  showWorkPhaseTabs?: boolean
  /** Page action beside the tabs: the "Commesse di Progetto" create button (spec 0431). */
  createAction?: ReactNode
  /** The legacy Commesse filters always in view, no quick-search (user request 2026-11-27); only the "Commesse" page. */
  pinnedFilters?: boolean
}

/**
 * The shared body of the work orders pages (spec 0359): the grid with the legacy row actions
 * (the eye, Responsabili e Partecipanti, the tab menu, via `useWorkOrderRowActions`, spec 0405)
 * and its dialogs. Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function WorkOrdersGrid({
  domain,
  rowScope,
  scope,
  showWorkPhaseTabs = false,
  pinnedFilters = false,
  createAction,
}: WorkOrdersGridProps) {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
  }, [])
  const workPhaseTabs = useWorkPhaseTabs(tableRef)
  // Every row opens in the module of its type, whichever grid lists it (2026-11-25).
  const { handleAction, resolveActionHref, iconMap, renderers, sheet, dialogs } = useWorkOrderRowActions({
    onMutated: refreshGrid,
  })

  return (
    <div className="flex flex-1 flex-col gap-4">
      {/*
        "Commesse" non offre una creazione: quella commessa nasce dentro il
        perimetro di un'offerta (spec 0093 D-13). "Commesse di Progetto" crea
        invece una commessa senza offerta (spec 0431), via `createAction`.
      */}
      <PageHeader
        actions={
          showWorkPhaseTabs ? (
            <WorkPhaseTabs active={workPhaseTabs.active} counts={workPhaseTabs.counts} onSelect={workPhaseTabs.select} />
          ) : (
            createAction
          )
        }
      />

      <TableView
        ref={tableRef}
        domain={domain}
        scope={scope}
        rowScope={rowScope}
        pinnedFilters={pinnedFilters}
        onFilterModelChange={workPhaseTabs.onFilterModelChange}
        onAggregatesChange={workPhaseTabs.onAggregatesChange}
        renderers={renderers}
        onAction={handleAction}
        resolveActionHref={resolveActionHref}
        iconMap={iconMap}
      />

      {sheet}
      {dialogs}
    </div>
  )
}

/** "Commesse" adapter: the `work-orders` grid narrowed to `processing` work orders. */
export function WorkOrdersTable() {
  return (
    <WorkOrdersGrid
      domain={WORK_ORDERS_DOMAIN}
      rowScope={PROCESSING_ROW_SCOPE}
      scope={ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE}
      showWorkPhaseTabs
      pinnedFilters
    />
  )
}
