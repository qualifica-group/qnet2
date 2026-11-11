import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { LayoutGrid } from 'lucide-react'
import { Tabs, TabsList, TabsTrigger, TABS_LIST_CLASS } from '@/components/ui/tabs'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import type { RequestReportCategory } from '@/features/request-management/report-api'
import { CategoryMoreButton, CategoryTabsPicker } from '@/features/category-tabs/category-tabs-picker'
import {
  MEASURE_ALL,
  MEASURE_MANAGE,
  MEASURE_MORE,
  useCategoryTabFit,
} from '@/features/category-tabs/use-category-tab-fit'
import { useDashboardCategoryStrip } from '@/features/request-management/use-dashboard-category-strip'
import { OVERVIEW_TAB } from '@/features/request-management/use-request-dashboard-tab'
import { cn } from '@/lib/utils'

/** Borderless list inside the trough, so the menu button shares the tabs' pill background (as in Gestione Richieste). */
const INLINE_TAB_LIST_CLASS = 'w-auto min-w-0 flex-nowrap gap-1 rounded-none border-0 bg-transparent p-0 shadow-none'

export interface DashboardCategoryStripProps {
  categories: RequestDashboardCategory[]
  /** The report branches: their depth tells a main category from a subcategory. */
  branches: RequestReportCategory[]
  /** The active tab: `OVERVIEW_TAB` or a category key. */
  value: string
  onSelect: (tab: string) => void
}

/**
 * The statistics tab strip, laid out like Gestione Richieste's (user
 * directive 2026-10-05): "Panoramica" first, then the categories that fit
 * inline; the rest fold into "Altre (N)", and the open category always stays
 * inline. The menu is Gestione Richieste's own (spec 0385): search, a star per
 * category, "only favourites", select/deselect all and the "parent
 * categories" switch, so it stays on the strip even when every tab fits.
 * Rendered INSIDE the dashboard's `Tabs` root, so every trigger still
 * controls its panel.
 */
export function DashboardCategoryStrip({ categories, branches, value, onSelect }: DashboardCategoryStripProps) {
  const { t } = useTranslation()
  const troughRef = useRef<HTMLDivElement>(null)
  const measureRef = useRef<HTMLDivElement>(null)
  const { stripCategories, levelCategories, candidateIds, selectedId, pickerProps } = useDashboardCategoryStrip({
    categories,
    branches,
    value,
    onSelect,
  })
  const inlineIds = useCategoryTabFit(troughRef, measureRef, stripCategories, candidateIds, selectedId)
  const levelIds = new Set(levelCategories.map((category) => category.id))
  const hiddenCount = levelCategories.length - inlineIds.filter((id) => levelIds.has(id)).length
  const overviewLabel = t('requestManagement.dashboard.overviewTab')

  return (
    // `w-0 min-w-full` keeps the strip off the page's intrinsic width (see `FormTabStrip`).
    <div className="relative w-0 min-w-full overflow-x-clip">
      <div ref={troughRef} className={cn(TABS_LIST_CLASS, 'flex flex-nowrap')}>
        <TabsList aria-label={t('requestManagement.dashboard.tabsLabel')} className={INLINE_TAB_LIST_CLASS}>
          <TabsTrigger value={OVERVIEW_TAB}>
            <LayoutGrid aria-hidden="true" />
            {overviewLabel}
          </TabsTrigger>
          {inlineIds.map((id) => {
            const category = stripCategories[id]
            return (
              <TabsTrigger key={category.key} value={category.key} title={category.name}>
                <span className="max-w-48 truncate">{category.name}</span>
              </TabsTrigger>
            )
          })}
        </TabsList>
        <CategoryTabsPicker {...pickerProps} hiddenCount={hiddenCount} />
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
            <TabsTrigger value={OVERVIEW_TAB} data-tab-measure={MEASURE_ALL}>
              <LayoutGrid aria-hidden="true" />
              {overviewLabel}
            </TabsTrigger>
            {stripCategories.map((category) => (
              <TabsTrigger key={category.key} value={category.key} data-tab-measure={String(category.id)}>
                <span className="max-w-48 truncate">{category.name}</span>
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
        <CategoryMoreButton hiddenCount={stripCategories.length} tabIndex={-1} data-tab-measure={MEASURE_MORE} />
        <CategoryMoreButton hiddenCount={0} tabIndex={-1} data-tab-measure={MEASURE_MANAGE} />
      </div>
    </div>
  )
}
