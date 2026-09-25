import type { ReactNode } from 'react'
import type { TFunction } from 'i18next'
import type { GridApi } from 'ag-grid-community'
import { Download } from 'lucide-react'
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { ACTIONS_COLUMN_ID } from '@/components/data-table/data-table'
import { ExportDialog } from '@/features/exports/export-dialog'
import { SavedViewsSlot } from '@/features/table/saved-views-slot'
import { RuleBuilderDialog } from '@/features/table/custom-filters/rule-builder-dialog'
import type { UseTableCustomFiltersResult } from '@/features/table/custom-filters/use-table-custom-filters'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'
import type { TableConfig, TableRow } from '@/features/table/types'

interface TableViewSlotsArgs {
  domain: string
  t: TFunction
  gridApi: GridApi<TableRow> | null
  config: TableConfig | undefined
  advancedFilters: UseAdvancedFiltersResult
  setFiltersCustomizedLocally: (hasFilters: boolean) => void
  customFilters: UseTableCustomFiltersResult
  canPublishFilterViews: boolean
  canExport: boolean
  exportOpen: boolean
  onExportOpen: () => void
  onExportOpenChange: (open: boolean) => void
  getSearchTerm: () => string
  opportunityId?: number
  quoteId?: number
}

interface TableViewSlots {
  savedViewsSlot: ReactNode
  exportSlot: ReactNode
  dialogs: ReactNode
}

/**
 * Assembles the toolbar's saved-views/export slots and the two dialogs they
 * open (export, custom filter rule builder — spec 0014/0158), given the state
 * `TableView` already owns. A plain factory function (not a hook: it calls no
 * hooks of its own), extracted purely to keep `table-view.tsx` under the
 * engineering.md §6 size budget.
 */
export function buildTableViewSlots({
  domain,
  t,
  gridApi,
  config,
  advancedFilters,
  setFiltersCustomizedLocally,
  customFilters,
  canPublishFilterViews,
  canExport,
  exportOpen,
  onExportOpen,
  onExportOpenChange,
  getSearchTerm,
  opportunityId,
  quoteId,
}: TableViewSlotsArgs): TableViewSlots {
  const savedViewsSlot = (
    <SavedViewsSlot
      domain={domain}
      gridApi={gridApi}
      config={config}
      advancedFilters={advancedFilters}
      onFilterModelApplied={setFiltersCustomizedLocally}
      onApplyRules={customFilters.applyRules}
      onNewCustomFilter={customFilters.openNewFilter}
      onEditCustomFilter={customFilters.openEditFilter}
      activeCustomFilterViewId={customFilters.active.state?.viewId}
      canPublish={canPublishFilterViews}
    />
  )

  const exportSlot = canExport ? (
    <DropdownMenuItem
      onSelect={(event) => {
        event.preventDefault()
        onExportOpen()
      }}
    >
      <Download aria-hidden="true" />
      {t('exports.action')}
    </DropdownMenuItem>
  ) : null

  const dialogs = (
    <>
      {canExport && config ? (
        <ExportDialog
          domain={domain}
          open={exportOpen}
          onOpenChange={onExportOpenChange}
          gridApi={gridApi}
          columns={config.columns}
          actionsColumnId={ACTIONS_COLUMN_ID}
          search={getSearchTerm()}
          advancedFilters={advancedFilters.activeValues}
          customFilterRules={customFilters.active.state?.rules ?? null}
          opportunityId={opportunityId}
          quoteId={quoteId}
        />
      ) : null}

      {config ? (
        <RuleBuilderDialog
          domain={domain}
          open={customFilters.builderOpen}
          onOpenChange={(next) => {
            if (!next) {
              customFilters.closeBuilder()
            }
          }}
          columns={config.columns}
          editingView={customFilters.editingView}
          canPublish={canPublishFilterViews}
          onApply={customFilters.applyRules}
        />
      ) : null}
    </>
  )

  return { savedViewsSlot, exportSlot, dialogs }
}
