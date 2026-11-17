import { useCallback, useRef } from 'react'
import { PageHeader } from '@/components/page-header'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE, type TableConfigScope } from '@/features/table/use-table-config'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import { useWorkPhaseTabs } from '@/features/work-orders/use-work-phase-tabs'
import { WorkPhaseTabs } from '@/features/work-orders/work-phase-tabs'

interface WorkOrdersGridProps {
  /** Grid domain: `work-orders` or the project-only `project-work-orders` (same columns and actions). */
  domain: string
  /** Config scope of the grid; only the "Commesse" page takes the filter-driven flexible-field columns (spec 0374). */
  scope?: TableConfigScope
  /** Shows the working-phase tabs at the top right; only the "Commesse" page has them. */
  showWorkPhaseTabs?: boolean
}

/**
 * The shared body of the work orders pages (spec 0359): the grid with the legacy row actions
 * (Modifica, Responsabili e Partecipanti, the tab menu, via `useWorkOrderRowActions`, spec 0405)
 * and its dialogs. Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function WorkOrdersGrid({ domain, scope, showWorkPhaseTabs = false }: WorkOrdersGridProps) {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
  }, [])
  const workPhaseTabs = useWorkPhaseTabs(tableRef)
  // The project-only grid has its own domain, but its rows open the Commessa pages.
  const { handleAction, resolveActionHref, iconMap, renderers, sheet, dialogs } = useWorkOrderRowActions({
    onMutated: refreshGrid,
  })

  return (
    <div className="flex flex-1 flex-col gap-4">
      {/*
        Nessuna azione di creazione qui: una commessa nasce sempre dentro il
        perimetro di un'offerta, quindi la pagina elenco non la offre (spec 0093
        D-13). Il PageHeader resta per il breadcrumb e per i tab di fase.
      */}
      <PageHeader
        actions={
          showWorkPhaseTabs ? (
            <WorkPhaseTabs active={workPhaseTabs.active} counts={workPhaseTabs.counts} onSelect={workPhaseTabs.select} />
          ) : undefined
        }
      />

      <TableView
        ref={tableRef}
        domain={domain}
        scope={scope}
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

/** "Commesse" adapter: the `work-orders` grid with every work order type, processing and project (user decision 2026-11-17, supersedes spec 0359 D-2). */
export function WorkOrdersTable() {
  return <WorkOrdersGrid domain={WORK_ORDERS_DOMAIN} scope={ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE} showWorkPhaseTabs />
}
