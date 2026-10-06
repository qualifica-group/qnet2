import { useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, LayoutGrid } from 'lucide-react'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import { filterCategoriesByName } from '@/features/request-management/category-tab-fit'
import { CategoryMoreButton } from '@/features/request-management/request-management-category-picker'
import { MEASURE_ALL, MEASURE_MORE, useCategoryTabFit } from '@/features/request-management/use-category-tab-fit'
import { OVERVIEW_TAB } from '@/features/request-management/use-request-dashboard-tab'
import { cn } from '@/lib/utils'

/** Borderless list inside the trough, so the "Altre" button shares the tabs' pill background (as in Gestione Richieste). */
const INLINE_TAB_LIST_CLASS = 'w-auto min-w-0 flex-nowrap gap-1 rounded-none border-0 bg-transparent p-0'

/**
 * The fit Gestione Richieste uses keys tabs by number: here that number is the
 * category's POSITION, so nothing depends on what a branch key looks like.
 */
interface StripCategory {
  id: number
  key: string
  name: string
}

interface DashboardCategoryMenuProps {
  categories: StripCategory[]
  hiddenCount: number
  selectedKey: string
  onSelect: (key: string) => void
}

/** The "Altre (N)" menu: every category, a type-ahead filter, Enter picks the first match. */
function DashboardCategoryMenu({ categories, hiddenCount, selectedKey, onSelect }: DashboardCategoryMenuProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const matches = filterCategoriesByName(categories, search)

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    setSearch('')
  }
  const pick = (key: string) => {
    onSelect(key)
    handleOpenChange(false)
  }
  const handleSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter' && matches.length > 0) {
      event.preventDefault()
      pick(matches[0].key)
    }
  }

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <CategoryMoreButton hiddenCount={hiddenCount} title={t('requestManagement.categoryTabs.pickerLabel')} />
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72 max-w-[calc(100vw-2rem)] p-1">
        <div className="p-1">
          <Input
            autoFocus
            aria-label={t('requestManagement.categoryTabs.searchPlaceholder')}
            placeholder={t('requestManagement.categoryTabs.searchPlaceholder')}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            onKeyDown={handleSearchKeyDown}
            className="h-8 text-xs"
          />
        </div>
        <div className="max-h-72 overflow-y-auto p-1">
          {matches.length === 0 ? (
            <p className="px-2 py-4 text-center text-xs text-muted-foreground">
              {t('requestManagement.categoryTabs.noMatch')}
            </p>
          ) : (
            <ul aria-label={t('requestManagement.categoryTabs.pickerLabel')}>
              {matches.map((category) => {
                const selected = category.key === selectedKey

                return (
                  <li key={category.key}>
                    <button
                      type="button"
                      aria-current={selected || undefined}
                      onClick={() => pick(category.key)}
                      className={cn(
                        'flex w-full min-w-0 items-center gap-2 rounded-sm px-2 py-1.5 text-left text-xs outline-none',
                        'hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50',
                        selected && 'font-medium text-primary',
                      )}
                    >
                      <span className="min-w-0 flex-1 truncate" title={category.name}>
                        {category.name}
                      </span>
                      <Check className={cn('size-3.5 shrink-0', !selected && 'invisible')} aria-hidden="true" />
                    </button>
                  </li>
                )
              })}
            </ul>
          )}
        </div>
      </PopoverContent>
    </Popover>
  )
}

export interface DashboardCategoryStripProps {
  categories: RequestDashboardCategory[]
  /** The active tab: `OVERVIEW_TAB` or a category key. */
  value: string
  onSelect: (tab: string) => void
}

/**
 * The statistics tab strip, laid out like Gestione Richieste's (user
 * directive 2026-10-05): "Panoramica" first, then the categories that fit
 * inline; the rest fold into "Altre (N)" with a search, and the open category
 * always stays inline. Rendered INSIDE the dashboard's `Tabs` root, so every
 * trigger still controls its panel. No favourites: their endpoint belongs to
 * Gestione Richieste's own permission, which a statistics-only user lacks.
 */
export function DashboardCategoryStrip({ categories, value, onSelect }: DashboardCategoryStripProps) {
  const { t } = useTranslation()
  const troughRef = useRef<HTMLDivElement>(null)
  const measureRef = useRef<HTMLDivElement>(null)
  // Memoized on the query data: the fit re-measures whenever this identity changes.
  const stripCategories = useMemo(
    () => categories.map((category, index) => ({ id: index, key: category.key, name: category.label })),
    [categories],
  )
  const selectedIndex = stripCategories.findIndex((category) => category.key === value)
  const selectedId = selectedIndex === -1 ? null : selectedIndex
  const inlineIds = useCategoryTabFit(
    troughRef,
    measureRef,
    stripCategories,
    stripCategories.map((category) => category.id),
    selectedId,
  )
    const hiddenCount = stripCategories.length - inlineIds.length
  const overviewLabel = t('requestManagement.dashboard.overviewTab')

  return (
    // `w-0 min-w-full` keeps the strip off the page's intrinsic width (see `FormTabStrip`).
    <div className="relative w-0 min-w-full overflow-x-clip">
      <div ref={troughRef} className={cn('flex items-center border border-border/60 bg-surface', FORM_TAB_LIST_CLASS)}>
        <TabsList aria-label={t('requestManagement.dashboard.tabsLabel')} className={INLINE_TAB_LIST_CLASS}>
          <TabsTrigger value={OVERVIEW_TAB} className={FORM_TAB_TRIGGER_CLASS}>
            <LayoutGrid aria-hidden="true" />
            {overviewLabel}
          </TabsTrigger>
          {inlineIds.map((id) => {
            const category = stripCategories[id]
            return (
              <TabsTrigger key={category.key} value={category.key} className={FORM_TAB_TRIGGER_CLASS} title={category.name}>
                <span className="max-w-48 truncate">{category.name}</span>
              </TabsTrigger>
            )
          })}
        </TabsList>
        {hiddenCount > 0 ? (
          <DashboardCategoryMenu
            categories={stripCategories}
            hiddenCount={hiddenCount}
            selectedKey={value}
            onSelect={onSelect}
          />
        ) : null}
      </div>

      {/* Measuring layer: every tab at its natural width, out of flow and out of
          the accessibility tree, so the fit can be recomputed at any width. */}
      <div
        ref={measureRef}
        aria-hidden="true"
        inert
        className="pointer-events-none invisible absolute top-0 left-0 flex w-max gap-1"
      >
        <Tabs value={value}>
          <TabsList className={INLINE_TAB_LIST_CLASS}>
            <TabsTrigger value={OVERVIEW_TAB} className={FORM_TAB_TRIGGER_CLASS} data-tab-measure={MEASURE_ALL}>
              <LayoutGrid aria-hidden="true" />
              {overviewLabel}
            </TabsTrigger>
            {stripCategories.map((category) => (
              <TabsTrigger
                key={category.key}
                value={category.key}
                className={FORM_TAB_TRIGGER_CLASS}
                data-tab-measure={String(category.id)}
              >
                <span className="max-w-48 truncate">{category.name}</span>
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
        <CategoryMoreButton hiddenCount={stripCategories.length} tabIndex={-1} data-tab-measure={MEASURE_MORE} />
      </div>
    </div>
  )
}
