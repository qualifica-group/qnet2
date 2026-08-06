import { useCallback, useEffect, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { AgGridReact } from 'ag-grid-react'
import type { ColDef, ICellRendererParams } from 'ag-grid-community'
import { AG_GRID_LOCALE_EN, AG_GRID_LOCALE_IT } from '@ag-grid-community/locale'
import { FileText } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildColDefs } from '@/components/data-table/column-def-builder'
import { buildDataTableTheme } from '@/components/data-table/data-table-theme'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useUiScale } from '@/features/appearance/ui-scale-context'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { QUOTES_ACTION_ICONS } from '@/features/quotes/action-icons'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import { quoteColumnRenderers } from '@/features/quotes/column-renderers'
import { useQuoteRowActions } from '@/features/quotes/use-quote-row-actions'
import { createRowActionsRenderer, INLINE_ACTION_LIMIT } from '@/features/table/row-actions'
import { useTableConfig } from '@/features/table/use-table-config'
import type { RowActionsRenderer } from '@/components/data-table/data-table'
import type { TableColumn, TableRow } from '@/features/table/types'
import {
  opportunityQuoteRowsQueryKey,
  useOpportunityQuoteRows,
} from '@/features/opportunities/use-opportunity-quote-rows'

// The panel mounts its own grid, independent of the master one; the bootstrap
// is idempotent, so calling it here removes any load-order assumption.
setupAgGrid()

/**
 * Bound height of the embedded grid. An expanded row must stay a glance at the
 * Offerte, not a second full page: past this the list scrolls inside the
 * panel while the master row keeps a stable, finite height.
 */
const PANEL_MAX_HEIGHT = 'max-h-[22rem]'

/**
 * The embedded grid's header sits one rung DOWN the surface ladder
 * (`--surface`) instead of sharing the grid's own `--card`, so the Offerte
 * header visibly breaks away from the Opportunity row above it (user directive
 * 2026-08-06). A token, never a hard-coded grey: it tracks dark mode like the
 * rest of the ladder (`ui-design.md §1-bis`).
 */
const PANEL_HEADER_BACKGROUND = 'var(--surface)'

/**
 * Strips every interactive column affordance the embedded panel does not own:
 * sorting/filtering (the rows are one server-ordered, capped extract — sorting
 * only what happens to be loaded would misrepresent the set) and inline editing
 * (the master grid's `DataTable` owns the PATCH/revert cycle; this panel does
 * not wire it, so an editable cell here would silently discard the edit).
 * Everything else — order, widths, visibility, renderers, formatters — is
 * whatever the Offerte table itself resolved for this actor.
 */
function toReadOnlyColDef(def: ColDef): ColDef {
  return { ...def, sortable: false, filter: false, editable: false }
}

interface QuotesPanelGridProps {
  columns: TableColumn[]
  rows: TableRow[]
  renderRowActions: RowActionsRenderer
  actionsColumnHasOverflow: boolean
}

/** The embedded, read-only Offerte grid: same colDefs pipeline as the Offerte table. */
function QuotesPanelGrid({
  columns,
  rows,
  renderRowActions,
  actionsColumnHasOverflow,
}: QuotesPanelGridProps) {
  const { t, i18n } = useTranslation()
  const { factor } = useUiScale()
  const theme = useMemo(
    () =>
      buildDataTableTheme(factor).withParams({
        headerBackgroundColor: PANEL_HEADER_BACKGROUND,
      }),
    [factor],
  )

  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const colDefs = useMemo(
    () =>
      buildColDefs({
        domain: QUOTES_DOMAIN,
        columns,
        cellRenderers: quoteColumnRenderers,
        renderRowActions,
        actionsHeaderLabel: 'table.actionsHeader',
        actionsColumnHasOverflow,
        t,
      }).map(toReadOnlyColDef),
    [columns, renderRowActions, actionsColumnHasOverflow, t],
  )

  return (
    // The shared theme drops the grid's own wrapper border (on the Offerte
    // page the toolbar card owns it); inside a detail row there is no such
    // card, so the panel draws its own edge.
    <div className={cn('overflow-auto rounded-lg border border-border', PANEL_MAX_HEIGHT)}>
      <AgGridReact
        theme={theme}
        columnDefs={colDefs}
        rowData={rows}
        localeText={localeText}
        // Custom-field columns use a dotted id as a FLAT row key, never a path.
        suppressFieldDotNotation
        suppressCellFocus
        domLayout="autoHeight"
      />
    </div>
  )
}

/** Skeleton placeholder mirroring the grid's shape while the lazy fetch is in flight. */
function PanelLoadingState() {
  return (
    <div className="flex flex-col gap-2 p-3">
      {Array.from({ length: 3 }).map((_, index) => (
        <Skeleton key={index} className="h-8 w-full" />
      ))}
    </div>
  )
}

/** Error state with a retry action: never a bare spinner, never a silently empty row. */
function PanelErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-start gap-3 p-4">
      <p className="text-sm text-destructive">{message}</p>
      <Button type="button" variant="outline" size="sm" onClick={onRetry}>
        {t('common.retry')}
      </Button>
    </div>
  )
}

/** Empty state: the Opportunity has no Offerta yet. */
function PanelEmptyState({ message }: { message: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center">
      <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <FileText aria-hidden="true" className="size-5" />
      </span>
      <p className="text-sm font-medium text-muted-foreground">{message}</p>
    </div>
  )
}

/**
 * AG Grid `detailCellRenderer` for the Opportunities master/detail: expanding
 * an Opportunity row reveals its Offerte, still as table rows, with the
 * COLUMNS AND VALUES of the Offerte table (user directive 2026-08-06).
 *
 * Nothing about those rows is re-derived here: the columns come from the
 * Offerte table config (`useTableConfig('quotes')` — same order/width/
 * visibility the actor sees on the Offerte page), the values from the same
 * `POST /tables/quotes/rows` endpoint scoped to this Opportunity, and the row
 * actions from the same catalog and the same behavior hook the Offerte page
 * uses (`useQuoteRowActions`), so the two can never drift apart. View/edit/
 * create are forced into a Sheet (`OPEN_MODE_MODAL`) so acting on an Offerta
 * never abandons the Opportunities list underneath.
 */
export function OpportunityQuotesDetailRenderer({
  data,
  node,
  api,
}: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const opportunityId = typeof data?.id === 'number' ? data.id : null

  const config = useTableConfig(QUOTES_DOMAIN)
  const quoteRows = useOpportunityQuoteRows(opportunityId ?? 0, {
    enabled: opportunityId != null,
  })

  // A delete (or a save from the Sheet) invalidates this panel's own cached
  // rows; the master grid's Opportunity row is untouched by design — nothing
  // on it is derived from the Offerte it holds.
  const queryClient = useQueryClient()
  const handleMutated = useCallback(() => {
    if (opportunityId != null) {
      void queryClient.invalidateQueries({
        queryKey: opportunityQuoteRowsQueryKey(opportunityId),
      })
    }
  }, [opportunityId, queryClient])

  const { handleAction, isBusy, activityRow, closeActivity, sheet } = useQuoteRowActions({
    onMutated: handleMutated,
    forceMode: OPEN_MODE_MODAL,
  })

  const actions = config.data?.actions
  const renderRowActions = useMemo(
    () =>
      actions
        ? createRowActionsRenderer(actions, handleAction, {
            isBusy,
            iconMap: QUOTES_ACTION_ICONS,
          })
        : undefined,
    [actions, handleAction, isBusy],
  )

  // AG Grid measures a detail row's auto-height when the detail cell first
  // mounts, and this panel loads lazily — so on the FIRST expand it is measured
  // as a short skeleton and would stay clipped once the rows arrive. Observing
  // the loaded content and pushing its real height back keeps the row fitted on
  // the first open too (same mechanism as `RewardDetailRenderer`).
  const contentRef = useRef<HTMLDivElement>(null)
  const isReady = config.isSuccess && quoteRows.isSuccess
  useEffect(() => {
    const element = contentRef.current
    if (!element || !node || !api || typeof ResizeObserver === 'undefined') {
      return
    }
    const observer = new ResizeObserver(() => {
      node.setRowHeight(element.offsetHeight)
      api.onRowHeightChanged()
    })
    observer.observe(element)
    return () => observer.disconnect()
  }, [node, api, isReady])

  if (opportunityId == null) {
    return null
  }

  if (config.isPending || quoteRows.isPending) {
    return <PanelLoadingState />
  }

  if (config.isError || quoteRows.isError) {
    return (
      <PanelErrorState
        message={t('opportunities.detail.quotes.loadError')}
        onRetry={() => {
          void (config.isError ? config.refetch() : quoteRows.refetch())
        }}
      />
    )
  }

  if (quoteRows.data.rows.length === 0) {
    return <PanelEmptyState message={t('opportunities.detail.quotes.empty')} />
  }

  const { rows, total } = quoteRows.data
  const isTruncated = total > rows.length

  return (
    <div ref={contentRef} className="p-3">
      {renderRowActions ? (
        <QuotesPanelGrid
          columns={config.data.columns}
          rows={rows}
          renderRowActions={renderRowActions}
          actionsColumnHasOverflow={config.data.actions.length > INLINE_ACTION_LIMIT}
        />
      ) : null}

      {isTruncated ? (
        <p className="px-1 pt-2 text-xs text-muted-foreground">
          {t('opportunities.detail.quotes.truncated', { shown: rows.length, total })}
        </p>
      ) : null}

      {sheet}

      <ResourceActivityDialog
        resource={QUOTES_DOMAIN}
        row={activityRow}
        onOpenChange={closeActivity}
      />
    </div>
  )
}
