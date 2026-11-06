import { useCallback, useRef, type ReactNode } from 'react'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { CategoryTabs } from '@/features/category-tabs/category-tabs'
import { useCategoryTabStrip } from '@/features/category-tabs/use-category-tab-strip'
import type { CategoryTabSource } from '@/features/category-tabs/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableConfigScope } from '@/features/table/use-table-config'
import type { TableRowScope } from '@/features/table/types'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import { WorkOrderProformaDialog } from '@/features/work-orders/work-order-proforma-dialog'

const CATEGORY_TAB_SOURCE: CategoryTabSource = { key: WORK_ORDERS_DOMAIN, apiBasePath: `/${WORK_ORDERS_DOMAIN}` }

/** Only the "Commesse" page lists `processing` work orders: project ones live under "Commesse di Progetto" (spec 0359 D-2). */
const PROCESSING_ROW_SCOPE: TableRowScope = { workOrderType: 'processing' }

interface WorkOrdersGridProps {
  /** Grid domain: `work-orders` or the project-only `project-work-orders` (same columns and actions). */
  domain: string
  rowScope?: TableRowScope
  scope?: TableConfigScope
  /** Remounts the table (tab change): search, filters and layout restart from the scoped config. */
  tableKey?: string | number
  /** Extra refresh after a mutation (e.g. the category strip counts). */
  onMutated?: () => void
  /** Rendered between the page header and the grid. */
  strip?: ReactNode
}

/**
 * The shared body of the work orders pages (spec 0359): the grid with every row action
 * (view/delete/activity/force close/reopen/proforma, via `useWorkOrderRowActions`, spec 0095 D-9)
 * and its dialogs. Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function WorkOrdersGrid({ domain, rowScope, scope, tableKey, onMutated, strip }: WorkOrdersGridProps) {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
    onMutated?.()
  }, [onMutated])

  const {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    proformaRow,
    closeProforma,
    resolveActionState,
    iconMap,
    renderers,
    sheet,
    forceCloseDialog,
    progressDialog,
    requestUpdateDialog,
    collaborationDialogs,
  } = useWorkOrderRowActions({ onMutated: refreshGrid })

  return (
    <div className="flex flex-1 flex-col gap-4">
      {/*
        Nessuna azione di creazione qui: una commessa nasce sempre dentro il
        perimetro di un'offerta, quindi la pagina elenco non la offre (spec 0093
        D-13). Il PageHeader resta per il breadcrumb.
      */}
      <PageHeader />

      {strip}

      <TableView
        key={tableKey}
        ref={tableRef}
        domain={domain}
        scope={scope}
        rowScope={rowScope}
        renderers={renderers}
        onAction={handleAction}
        isBusy={isBusy}
        resolveActionState={resolveActionState}
        iconMap={iconMap}
      />

      {sheet}
      {forceCloseDialog}
      {progressDialog}
      {requestUpdateDialog}
      {collaborationDialogs}

      <WorkOrderProformaDialog row={proformaRow} onClose={closeProforma} onSent={refreshGrid} />

      <ResourceActivityDialog resource={WORK_ORDERS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
    </div>
  )
}

/**
 * "Commesse" adapter: the `work-orders` grid narrowed to `processing` work orders, with the
 * category strip above it (spec 0335 rev.3) scoping its rows to the source offers' revenue categories.
 */
export function WorkOrdersTable() {
  const { scope, tableKey, tabsProps, invalidateCategories } = useCategoryTabStrip(CATEGORY_TAB_SOURCE)

  return (
    <WorkOrdersGrid
      domain={WORK_ORDERS_DOMAIN}
      rowScope={PROCESSING_ROW_SCOPE}
      scope={scope}
      tableKey={tableKey}
      onMutated={invalidateCategories}
      strip={<CategoryTabs {...tabsProps} />}
    />
  )
}
