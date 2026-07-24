/* eslint-disable react-refresh/only-export-components -- grid-config module: SIDE_BAR/ACTIONS_COLUMN_ID are plain constants shared with data-table.tsx, not routable components */
/**
 * Overlay/placeholder pieces of the AG Grid wrapper (`data-table.tsx`), split
 * out to keep that file within the engineering size limits (`.claude/rules/
 * engineering.md` §6). Re-exported from `data-table.tsx` so every existing
 * import path (`@/components/data-table/data-table`) keeps working unchanged.
 */
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams, SideBarDef } from 'ag-grid-community'
import { Inbox } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'

/** Column id of the synthetic, left-pinned row-actions column (mirrors `data-table.tsx`'s export). */
export const ACTIONS_COLUMN_ID = '__actions'

/**
 * Right-hand tool panel listing every column with a checkbox to show/hide it and
 * drag handles to reorder. Closed on mount (opened from the vertical tab strip)
 * so the grid keeps its full width by default.
 *
 * Only the columns panel is exposed: the filters panel would duplicate the
 * per-header filter menus, and row-group/pivot/aggregation are meaningless under
 * the SSRM setup here, so their sections are suppressed rather than shown empty.
 */
export const SIDE_BAR: SideBarDef = {
  toolPanels: [
    {
      id: 'columns',
      labelDefault: 'Columns',
      labelKey: 'columns',
      iconKey: 'columns',
      toolPanel: 'agColumnsToolPanel',
      toolPanelParams: {
        suppressRowGroups: true,
        suppressValues: true,
        suppressPivots: true,
        suppressPivotMode: true,
      },
    },
  ],
  defaultToolPanel: undefined,
}

/**
 * Per-cell loading placeholder shown while an SSRM block streams in. Because AG
 * Grid renders it once per cell of every loading row, the skeleton naturally
 * follows the column layout (one bar per column) without us knowing the data.
 * The leading actions column gets a narrower bar so the row reads as content.
 */
export function SkeletonLoadingCell({ colDef }: ICellRendererParams) {
  const width = colDef?.colId === ACTIONS_COLUMN_ID ? 'w-12' : 'w-[70%]'
  return (
    <div className="flex h-full items-center">
      <Skeleton className={`h-4 ${width}`} />
    </div>
  )
}

/**
 * "No rows" overlay: an inbox glyph in a soft disc plus a localized message,
 * replacing AG Grid's plain default text so an empty grid reads as an
 * intentional state rather than a blank surface. Rendered by AG Grid inside the
 * React tree, so `useTranslation` works and it tracks the active language.
 */
export function TableEmptyOverlay() {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center">
      <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <Inbox aria-hidden="true" className="size-5" />
      </span>
      <p className="text-sm font-medium text-muted-foreground">{t('table.noRows')}</p>
    </div>
  )
}
