import { type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  FilterX,
  Funnel,
  Maximize2,
  Minimize2,
  MoreHorizontal,
  RotateCcw,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

export interface TableToolbarProps {
  /** Total known row count, or null before the first load. */
  rowCount: number | null
  /** Whether any column filter is active (drives the reset-filters affordance). */
  filtersActive: boolean
  onResetFilters: () => void
  resettingFilters: boolean
  /** Whether the column layout is customized (drives the reset-layout option). */
  layoutCustomized: boolean
  onResetLayout: () => void
  resettingLayout: boolean
  /** Fullscreen state + its toggle. */
  fullscreen: boolean
  onToggleFullscreen: () => void
  /**
   * Import action (rendered by the caller, which owns the permission gate and
   * the dialog), rendered as a `DropdownMenuItem` inside the options menu.
   * Purely a slot: the toolbar renders it without knowing about domains,
   * permissions or the import feature (spec 0012).
   */
  importSlot?: ReactNode
  /**
   * Export action (rendered by the caller, which owns the permission gate and
   * the dialog), rendered as a `DropdownMenuItem` inside the options menu,
   * next to `importSlot`. Purely a slot: the toolbar renders it without
   * knowing about domains, permissions or the export feature (spec 0014).
   */
  exportSlot?: ReactNode
  /**
   * Bulk-actions affordance for the current selection (rendered by the caller,
   * which owns the selection state and the delete mutation). Purely a slot:
   * the toolbar renders it next to the row count without knowing about
   * selection or bulk delete.
   */
  bulkActionsSlot?: ReactNode
  /** The applied-filter chips with their "clear all", right after the row count (pinned filters only). */
  filterChipsSlot?: ReactNode
  /** The funnel beside the options menu that shows/hides the pinned filters; absent = no funnel. */
  filtersToggle?: FiltersToggle
}

export interface FiltersToggle {
  open: boolean
  /** Applied filters, counted on the funnel while they are hidden. */
  activeCount: number
  onToggle: () => void
}

interface IconButtonProps {
  icon: ReactNode
  label: string
  onClick: () => void
  disabled?: boolean
}

/** A ghost, icon-only toolbar button with a tooltip (the toolbar's base unit). */
function IconButton({ icon, label, onClick, disabled }: IconButtonProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={label}
          disabled={disabled}
          onClick={onClick}
          className="text-muted-foreground hover:text-foreground"
        >
          {icon}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

/** Funnel toggle of the pinned filters: pressed while they show, the active count while hidden. */
function FiltersToggleButton({ open, activeCount, onToggle }: FiltersToggle) {
  const { t } = useTranslation()
  const label = t(open ? 'table.hideFilters' : 'table.showFilters')
  const showCount = !open && activeCount > 0
  const accessibleLabel = showCount ? `${label}, ${t('table.advancedFilters.activeCount', { count: activeCount })}` : label

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={accessibleLabel}
          aria-pressed={open}
          onClick={onToggle}
          className="relative text-muted-foreground hover:text-foreground aria-pressed:bg-accent aria-pressed:text-foreground"
        >
          <Funnel aria-hidden="true" />
          {showCount ? (
            <Badge
              variant="destructive"
              aria-hidden="true"
              className="absolute -right-1 -top-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] tabular-nums"
            >
              {activeCount}
            </Badge>
          ) : null}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

/**
 * The unified table header (spec 0009): a single bar fused to the top of the
 * grid. Left holds the live row counter (the quick-search and the advanced
 * filters and the saved filters sit above the grid, in `TableFilterBar`);
 * right holds the icon controls — reset-filters, the funnel of the pinned
 * filters (when the page has them), an options menu (import, export, reset
 * layout) and fullscreen. Purely presentational: every piece
 * of state and every handler is owned by the caller (TableView).
 */
export function TableToolbar({
  rowCount,
  filtersActive,
  onResetFilters,
  resettingFilters,
  layoutCustomized,
  onResetLayout,
  resettingLayout,
  fullscreen,
  onToggleFullscreen,
  importSlot,
  exportSlot,
  bulkActionsSlot,
  filterChipsSlot,
  filtersToggle,
}: TableToolbarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-2 border-b border-border bg-card px-2.5 py-2">
      {/* Left: live row count + bulk actions + (pinned filters) the applied-filter chips */}
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1.5">
        {rowCount != null ? (
          <span className="hidden shrink-0 whitespace-nowrap text-xs font-medium text-muted-foreground sm:inline">
            {t('table.rowCount', { count: rowCount })}
          </span>
        ) : null}

        {bulkActionsSlot}

        {filterChipsSlot}
      </div>

      {/* Right: icon controls */}
      <div className="flex shrink-0 items-center gap-0.5">
        {filtersActive ? (
          <IconButton
            icon={<FilterX aria-hidden="true" />}
            label={t('table.resetFilters')}
            onClick={onResetFilters}
            disabled={resettingFilters}
          />
        ) : null}

        {filtersToggle ? <FiltersToggleButton {...filtersToggle} /> : null}

        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('table.options')}
                  className="text-muted-foreground hover:text-foreground"
                >
                  <MoreHorizontal aria-hidden="true" />
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>{t('table.options')}</TooltipContent>
          </Tooltip>

          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
              {t('table.options')}
            </DropdownMenuLabel>

            {importSlot}

            {exportSlot}

            {layoutCustomized ? (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  disabled={resettingLayout}
                  onSelect={(event) => {
                    event.preventDefault()
                    onResetLayout()
                  }}
                >
                  <RotateCcw aria-hidden="true" />
                  {t('table.resetLayout')}
                </DropdownMenuItem>
              </>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>

        <Separator orientation="vertical" className="mx-0.5 h-5" />

        <IconButton
          icon={
            fullscreen ? (
              <Minimize2 aria-hidden="true" />
            ) : (
              <Maximize2 aria-hidden="true" />
            )
          }
          label={t(fullscreen ? 'table.exitFullscreen' : 'table.fullscreen')}
          onClick={onToggleFullscreen}
        />
      </div>
    </div>
  )
}
