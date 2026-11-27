import { type ReactNode, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { Search, SlidersHorizontal, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { AdvancedFilterPanel, ADVANCED_FILTER_PANEL_ANIMATION } from '@/features/table/advanced-filters/advanced-filter-panel'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { UseAdvancedFiltersResult } from '@/features/table/advanced-filters/use-advanced-filters'

/**
 * Hard cap of the quick-search field, mirroring the server-side
 * `TableRowsRequest::SEARCH_MAX_LENGTH`. Enforced here so pasting a longer term
 * can never produce a 422 the SSRM datasource can only report as a row of "ERR"
 * cells — the two values must stay in lockstep.
 */
export const SEARCH_MAX_LENGTH = 255

export interface TableFilterBarProps {
  /** Whether the domain exposes a global quick-search (config `searchable`). */
  searchEnabled: boolean
  /** Placeholder built from the searchable columns' labels. */
  searchPlaceholder: string
  /** Ref to the search input so the ⌘K shortcut can focus it. */
  searchInputRef: RefObject<HTMLInputElement | null>
  searchValue: string
  onSearchChange: (value: string) => void
  /** Localized keyboard-shortcut hint shown in the search field (⌘K / Ctrl K). */
  searchShortcut: string
  /** The domain's advanced filter catalog (spec 0032); empty hides the toggle and the panel. */
  advancedFilterDescriptors: AdvancedFilterDescriptor[]
  advancedFilters: UseAdvancedFiltersResult
  advancedFiltersOpen: boolean
  onToggleAdvancedFilters: () => void
  /**
   * The filters straight on the page, with no frame or Reset/Apply
   * (`TableViewProps.pinnedFilters`): they apply by themselves, and the funnel
   * in the table header (`TableToolbar.filtersToggle`) shows/hides them,
   * saved filters included, through `advancedFiltersOpen`.
   */
  advancedFiltersPinned?: boolean
  /** Saved-views control (rendered by the caller, which owns the grid API). */
  savedViewsSlot?: ReactNode
}

/**
 * The filtering block that sits ABOVE the grid, apart from it: the global
 * quick-search, the advanced-filters toggle, the saved filters and, under them,
 * the collapsible advanced-filters panel. Renders nothing when there is none of
 * them. Purely
 * presentational: state and handlers are owned by the caller (TableView).
 */
export function TableFilterBar({
  searchEnabled,
  searchPlaceholder,
  searchInputRef,
  searchValue,
  onSearchChange,
  searchShortcut,
  advancedFilterDescriptors,
  advancedFilters,
  advancedFiltersOpen,
  onToggleAdvancedFilters,
  advancedFiltersPinned = false,
  savedViewsSlot,
}: TableFilterBarProps) {
  const { t } = useTranslation()
  const advancedFiltersEnabled = advancedFilterDescriptors.length > 0
  const { activeCount } = advancedFilters
  // Spelled out: the badge glued to the label would read "Filtri avanzati3 filtri attivi".
  const toggleLabel =
    activeCount > 0
      ? `${t('table.advancedFilters.toggle')}, ${t('table.advancedFilters.activeCount', { count: activeCount })}`
      : t('table.advancedFilters.toggle')

  if (!searchEnabled && !advancedFiltersEnabled && savedViewsSlot == null) {
    return null
  }

  if (advancedFiltersPinned && advancedFiltersEnabled) {
    // The saved filters show and hide with the fields (user request 2026-11-27).
    return (
      <Collapsible open={advancedFiltersOpen}>
        <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
          <div className="flex flex-wrap items-end gap-3">
            <AdvancedFilterPanel descriptors={advancedFilterDescriptors} filters={advancedFilters} bare className="min-w-0 flex-1" />
            {savedViewsSlot ? <div className="ml-auto flex items-center gap-2">{savedViewsSlot}</div> : null}
          </div>
        </CollapsibleContent>
      </Collapsible>
    )
  }

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div className="flex flex-wrap items-center gap-2 px-2.5 py-2">
        {searchEnabled ? (
          <div className="relative w-full min-w-0 flex-1 sm:max-w-xs">
            <Search
              aria-hidden="true"
              className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              ref={searchInputRef}
              type="search"
              value={searchValue}
              onChange={(event) => onSearchChange(event.target.value)}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
              autoComplete="off"
              maxLength={SEARCH_MAX_LENGTH}
              className="h-9 border-transparent bg-muted/60 pl-8 pr-14 shadow-none focus-visible:border-ring focus-visible:bg-card [&::-webkit-search-cancel-button]:hidden"
            />
            {searchValue ? (
              <button
                type="button"
                onClick={() => onSearchChange('')}
                aria-label={t('common.clear')}
                className="absolute right-2 top-1/2 flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              >
                <X aria-hidden="true" className="size-3.5" />
              </button>
            ) : (
              <kbd className="pointer-events-none absolute right-2 top-1/2 hidden -translate-y-1/2 select-none items-center gap-0.5 rounded border bg-background px-1.5 font-mono text-[10px] font-medium text-muted-foreground sm:inline-flex">
                {searchShortcut}
              </kbd>
            )}
          </div>
        ) : null}

        {/* Pushed right; wrapping under the search on a narrow screen, still right-aligned. */}
        <div className="ml-auto flex items-center gap-2">
          {advancedFiltersEnabled ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-1.5 bg-card"
              aria-label={toggleLabel}
              aria-pressed={advancedFiltersOpen}
              onClick={onToggleAdvancedFilters}
            >
              <SlidersHorizontal aria-hidden="true" className="size-3.5" />
              {t('table.advancedFilters.toggle')}
              {activeCount > 0 ? (
                <Badge
                  variant="destructive"
                  className="h-4 min-w-4 justify-center rounded-full px-1 text-[10px] tabular-nums"
                  aria-hidden="true"
                >
                  {activeCount}
                </Badge>
              ) : null}
            </Button>
          ) : null}

          {savedViewsSlot}
        </div>
      </div>

      {advancedFiltersEnabled ? (
        <Collapsible open={advancedFiltersOpen}>
          <CollapsibleContent className={ADVANCED_FILTER_PANEL_ANIMATION}>
            <AdvancedFilterPanel
              descriptors={advancedFilterDescriptors}
              filters={advancedFilters}
              className="border-b-0 border-t"
            />
          </CollapsibleContent>
        </Collapsible>
      ) : null}
    </div>
  )
}
