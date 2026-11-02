import { useCallback, useRef } from 'react'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { CategoryTabs } from '@/features/category-tabs/category-tabs'
import { useCategoryTabStrip } from '@/features/category-tabs/use-category-tab-strip'
import type { CategoryTabSource } from '@/features/category-tabs/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import { WorkOrderProformaDialog } from '@/features/work-orders/work-order-proforma-dialog'

const CATEGORY_TAB_SOURCE: CategoryTabSource = { key: WORK_ORDERS_DOMAIN, apiBasePath: `/${WORK_ORDERS_DOMAIN}` }

/**
 * Thin work-orders adapter over the generic table. It mounts `<TableView>`
 * with the `work-orders` domain and its custom cell renderers, delegating
 * every row action (view/delete/activity/force close/reopen/proforma) to `useWorkOrderRowActions`
 * (spec 0095 D-9) — the same behavior the Contract detail's Commesse tab
 * uses, so the two can never drift. Permission gating is an affordance only;
 * the backend re-authorizes each call. The category strip above the grid
 * (spec 0335 rev.3) scopes its rows to the source offers' revenue categories.
 */
export function WorkOrdersTable() {
  const { scope, tableKey, tabsProps, invalidateCategories } = useCategoryTabStrip(CATEGORY_TAB_SOURCE)
  const tableRef = useRef<TableViewHandle>(null)
  // A delete or a save moves the strip counts, so they are re-read with the rows.
  const refreshGrid = useCallback(() => {
    tableRef.current?.refresh()
    invalidateCategories()
  }, [invalidateCategories])

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
  } = useWorkOrderRowActions({ onMutated: refreshGrid })

  return (
    <div className="flex flex-1 flex-col gap-4">
      {/*
        Nessuna azione di creazione qui: una commessa nasce sempre dentro il
        perimetro di un'offerta, quindi la pagina elenco non la offre (spec 0093
        D-13). Il PageHeader resta per il breadcrumb.
      */}
      <PageHeader />

      <CategoryTabs {...tabsProps} />

      <TableView
        // Keyed by the selection: a tab change remounts the table so search,
        // filters and layout restart from the scoped config.
        key={tableKey}
        ref={tableRef}
        domain={WORK_ORDERS_DOMAIN}
        scope={scope}
        renderers={renderers}
        onAction={handleAction}
        isBusy={isBusy}
        resolveActionState={resolveActionState}
        iconMap={iconMap}
      />

      {sheet}
      {forceCloseDialog}
      {progressDialog}

      <WorkOrderProformaDialog row={proformaRow} onClose={closeProforma} onSent={refreshGrid} />

      <ResourceActivityDialog
        resource={WORK_ORDERS_DOMAIN}
        row={activityRow}
        onOpenChange={closeActivity}
      />
    </div>
  )
}
