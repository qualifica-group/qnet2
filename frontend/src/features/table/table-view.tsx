import { forwardRef, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DataTable } from '@/components/data-table/data-table'
import { useTableConfig } from '@/features/table/use-table-config'
import { TableToolbar } from '@/features/table/table-toolbar'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { INLINE_ACTION_LIMIT, LABELED_ACTIONS_COLUMN_WIDTH } from '@/features/table/row-actions'
import { ActiveFilterChips } from '@/features/table/custom-filters/active-filter-chips'
import { buildTableViewSlots } from '@/features/table/table-view-slots'
import { useTableViewController, type TableViewHandle } from '@/features/table/use-table-view-controller'
import type { TableViewProps } from '@/features/table/table-view-props'

export type { TableViewHandle } from '@/features/table/use-table-view-controller'

/**
 * Generic, domain-driven table. Given a `domain`, it loads the backend config,
 * builds the SSRM datasource, and mounts the agnostic DataTable wrapper fused
 * under a single unified toolbar (spec 0009: search + row count on the left;
 * filter toggle, saved views, options and fullscreen on the right — one bordered
 * block, no detached buttons). It owns loading/error/empty states, the SSRM
 * refresh mechanism, and the toolbar's client state (search term, floating
 * filters, fullscreen), but holds NO domain logic: custom rendering and action
 * behavior arrive entirely via props (`renderers`, `onAction`, `isBusy`,
 * `decorateRow`). The state wiring itself lives in `useTableViewController`
 * (engineering.md §6) — this component stays a thin render orchestrator.
 *
 * Adding a new domain requires no change here — only a new adapter that mounts
 * this component with its `domain`, renderer map and action handler.
 */
export const TableView = forwardRef<TableViewHandle, TableViewProps>(
  function TableView(
    {
      domain,
      scope,
      rowScope,
      onRowCountChanged,
      renderers,
      onAction,
      isBusy,
      decorateRow,
      iconMap,
      labeledActions,
      importSlot,
      isRowSelectable,
      getBulkActions,
      disableBuiltinDelete,
      masterDetail,
      detailCellRenderer,
      detailRowAutoHeight,
      advancedFiltersOverride,
      onAdvancedFiltersOverrideCleared,
      renderFooter,
      pinnedRowSlot,
      interceptCellCommit,
      treeData,
    },
    ref,
  ) {
    const { t } = useTranslation()
    // Read once as a primitive: every downstream memo in the controller keys
    // on this value, not on the `scope`/`rowScope` object identity (a caller
    // may pass a fresh object literal every render).
    const productCategoryId = scope?.productCategoryId
    const opportunityId = rowScope?.opportunityId
    const quoteId = rowScope?.quoteId
    const { data: config, isPending, isError, refetch } = useTableConfig(domain, scope)

    const view = useTableViewController(
      {
        domain,
        scope,
        productCategoryId,
        opportunityId,
        quoteId,
        onRowCountChanged,
        onAction,
        isBusy,
        decorateRow,
        iconMap,
        labeledActions,
        getBulkActions,
        disableBuiltinDelete,
        advancedFiltersOverride,
        onAdvancedFiltersOverrideCleared,
        treeData,
        config,
        refetchConfig: refetch,
        t,
      },
      ref,
    )

    let content: ReactNode
    if (isPending) {
      content = (
        <div className="flex h-full flex-col gap-2 p-3">
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} className="h-8 w-full" />
          ))}
        </div>
      )
    } else if (isError) {
      content = (
        <div className="flex h-full flex-col items-start gap-3 p-4">
          <p className="text-sm text-destructive">{t('table.loadError')}</p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )
    } else if (config.columns.length === 0) {
      content = (
        <p className="p-4 text-sm text-muted-foreground">
          {t('table.emptyConfig')}
        </p>
      )
    } else {
      content = (
        <DataTable
          key={view.layoutVersion}
          domain={domain}
          productCategoryId={productCategoryId}
          opportunityId={opportunityId}
          quoteId={quoteId}
          columns={config.columns}
          datasource={view.datasource}
          blockSize={config.defaultPagination.limit}
          cellRenderers={renderers}
          renderRowActions={view.renderRowActions}
          actionsHeaderLabel="table.actionsHeader"
          actionsColumnHasOverflow={config.actions.length > INLINE_ACTION_LIMIT}
          actionsColumnWidth={labeledActions ? LABELED_ACTIONS_COLUMN_WIDTH : undefined}
          onGridReady={view.handleGridReady}
          onColumnStateChanged={view.handleColumnStateChanged}
          initialFilterModel={view.initialFilterModel}
          onFilterChanged={view.handleGridFilterChanged}
          onRowCountChanged={view.handleRowCountChanged}
          enableSelection={view.enableSelection}
          onSelectionChanged={view.handleSelectionChanged}
          isRowSelectable={isRowSelectable}
          masterDetail={masterDetail}
          detailCellRenderer={detailCellRenderer}
          detailRowAutoHeight={detailRowAutoHeight}
          interceptCellCommit={interceptCellCommit}
          treeData={treeData}
        />
      )
    }

    const footer = renderFooter ? renderFooter(view.aggregates) : null

    const { savedViewsSlot, exportSlot, dialogs } = buildTableViewSlots({
      domain,
      t,
      gridApi: view.gridApi,
      config,
      advancedFilters: view.advancedFilters,
      setFiltersCustomizedLocally: view.setFiltersCustomizedLocally,
      customFilters: view.customFilters,
      canPublishFilterViews: view.canPublishFilterViews,
      canExport: view.canExport,
      exportOpen: view.exportOpen,
      onExportOpen: () => view.setExportOpen(true),
      onExportOpenChange: view.setExportOpen,
      getSearchTerm: view.toolbar.getSearchTerm,
      opportunityId,
      quoteId,
    })

    return (
      <>
        <div
          className={cn(
            'flex min-h-0 flex-col',
            view.toolbar.fullscreen &&
              'fixed inset-0 z-50 bg-background/80 p-3 backdrop-blur-sm sm:p-4',
          )}
        >
          <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <TableToolbar
              searchEnabled={view.searchEnabled}
              searchPlaceholder={view.searchPlaceholder}
              searchInputRef={view.toolbar.searchInputRef}
              searchValue={view.toolbar.searchInput}
              onSearchChange={view.toolbar.setSearchInput}
              searchShortcut={view.toolbar.searchShortcut}
              rowCount={view.toolbar.rowCount}
              bulkActionsSlot={view.bulkActionsSlot}
              filtersActive={view.isFilterCustomized}
              onResetFilters={() => void view.handleResetFilters()}
              resettingFilters={view.resettingFilters}
              layoutCustomized={view.isCustomized}
              onResetLayout={() => void view.handleResetLayout()}
              resettingLayout={view.resettingLayout}
              fullscreen={view.toolbar.fullscreen}
              onToggleFullscreen={view.toolbar.toggleFullscreen}
              advancedFiltersEnabled={view.advancedFilterDescriptors.length > 0}
              advancedFiltersOpen={view.toolbar.advancedFiltersOpen}
              onToggleAdvancedFilters={view.toolbar.toggleAdvancedFilters}
              advancedFiltersActiveCount={view.advancedFilters.activeCount}
              savedViewsSlot={savedViewsSlot}
              importSlot={importSlot}
              exportSlot={exportSlot}
            />

            <ActiveFilterChips chips={view.filterChips.chips} onClearAll={view.filterChips.onClearAll} />

            {view.advancedFilterDescriptors.length > 0 ? (
              <Collapsible open={view.toolbar.advancedFiltersOpen}>
                <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
                  <AdvancedFilterPanel
                    descriptors={view.advancedFilterDescriptors}
                    filters={view.advancedFilters}
                  />
                </CollapsibleContent>
              </Collapsible>
            ) : null}

            <div
              ref={view.gridContainerRef}
              className={cn('min-h-0 w-full', view.toolbar.fullscreen && 'flex-1')}
              style={view.gridHeight === null ? undefined : { height: view.gridHeight }}
            >
              {content}
            </div>

            {pinnedRowSlot}
            {footer ? <div className="border-t border-border px-3 py-1.5">{footer}</div> : null}
          </div>
        </div>

        {dialogs}
      </>
    )
  },
)
