import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { cn } from '@/lib/utils'
import {
  CategoryMoreButton,
  RequestManagementCategoryPicker,
} from '@/features/request-management/request-management-category-picker'
import { arrangeCategoryTabs } from '@/features/request-management/category-tab-fit'
import {
  MEASURE_ALL,
  MEASURE_MANAGE,
  MEASURE_MORE,
  useCategoryTabFit,
} from '@/features/request-management/use-category-tab-fit'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/** The "Tutte" tab's Radix value; every category tab's value is its numeric id, stringified. */
const ALL_TAB_VALUE = 'all'

/**
 * The inner tab list sits borderless inside the trough, so the "Altre" button
 * shares the same pill background as the tabs. Not clipped: the fit keeps it
 * within the trough, and clipping would cut the active chip's ring.
 */
const INLINE_TAB_LIST_CLASS = 'w-auto min-w-0 flex-nowrap gap-1 rounded-none border-0 bg-transparent p-0'

interface RequestManagementCategoryTabsProps {
  categories: RequestManagementProductCategory[]
  /** `null` selects "Tutte". */
  selectedCategoryId: number | null
  onSelect: (categoryId: number | null) => void
  /** The actor's favourites (spec 0184): shown first, or alone with `showOnlyFavorites`. */
  favoriteCategoryIds: number[]
  showOnlyFavorites: boolean
  onToggleFavorite: (categoryId: number) => void
  onShowOnlyFavoritesChange: (showOnlyFavorites: boolean) => void
}

/**
 * Category tab strip above the Gestione Richieste grid (spec 0064): "Tutte"
 * always first and selected by default, then one tab per Product Category
 * actually present in the operator's visible requests, each carrying its own
 * request count. Presentation only — the parent owns persistence and derives
 * the table's scope from the selection (D-4: a scope change remounts the
 * whole table).
 *
 * Priority+ layout: the tabs that fit stay inline, the rest fold into an
 * "Altre (N)" menu with search, and the selected category always stays inline.
 * The actor's favourites come first, or alone when "only favourites" is on
 * (spec 0184); the menu, which also manages them, is always reachable.
 * Compact per ui-design.md §2 (`text-xs`, `px-2.5 py-1`, `size-3.5` icons).
 */
export function RequestManagementCategoryTabs({
  categories,
  selectedCategoryId,
  onSelect,
  favoriteCategoryIds,
  showOnlyFavorites,
  onToggleFavorite,
  onShowOnlyFavoritesChange,
}: RequestManagementCategoryTabsProps) {
  const { t } = useTranslation()
  const troughRef = useRef<HTMLDivElement>(null)
  const measureRef = useRef<HTMLDivElement>(null)
  const strip = arrangeCategoryTabs({
    categories,
    favoriteCategoryIds,
    showOnlyFavorites,
    selectedCategoryId,
  })
  const inlineIds = useCategoryTabFit(
    troughRef,
    measureRef,
    categories,
    strip.map((category) => category.id),
    selectedCategoryId,
  )

  const value = selectedCategoryId === null ? ALL_TAB_VALUE : String(selectedCategoryId)
  const selectValue = (next: string) => onSelect(next === ALL_TAB_VALUE ? null : Number(next))
  const byId = new Map(categories.map((category) => [category.id, category]))
  const hiddenCount = categories.length - inlineIds.length

  return (
    // `w-0 min-w-full` keeps the strip off its column's intrinsic width, so a
    // long strip never widens the app shell's `main` (see `FormTabStrip`).
    <div className="relative w-0 min-w-full overflow-x-clip">
      <Tabs value={value} onValueChange={selectValue}>
        <div
          ref={troughRef}
          className={cn('flex items-center border border-border/60 bg-surface', FORM_TAB_LIST_CLASS)}
        >
          <TabsList aria-label={t('requestManagement.categoryTabs.pickerLabel')} className={INLINE_TAB_LIST_CLASS}>
            <TabsTrigger value={ALL_TAB_VALUE} className={FORM_TAB_TRIGGER_CLASS}>
              {t('requestManagement.categoryTabs.all')}
            </TabsTrigger>
            {inlineIds.map((id) => (
              <CategoryTab key={id} category={byId.get(id)!} />
            ))}
          </TabsList>
          <RequestManagementCategoryPicker
            categories={categories}
            favoriteCategoryIds={favoriteCategoryIds}
            showOnlyFavorites={showOnlyFavorites}
            hiddenCount={hiddenCount}
            selectedCategoryId={selectedCategoryId}
            onSelect={onSelect}
            onToggleFavorite={onToggleFavorite}
            onShowOnlyFavoritesChange={onShowOnlyFavoritesChange}
          />
        </div>
      </Tabs>

      {/* Measuring layer: every tab at its natural width, out of flow and out
          of the accessibility tree, so the fit can be recomputed at any width. */}
      <div
        ref={measureRef}
        aria-hidden="true"
        inert
        className="pointer-events-none invisible absolute top-0 left-0 flex w-max gap-1"
      >
        <Tabs value={value}>
          <TabsList className={INLINE_TAB_LIST_CLASS}>
            <TabsTrigger value={ALL_TAB_VALUE} className={FORM_TAB_TRIGGER_CLASS} data-tab-measure={MEASURE_ALL}>
              {t('requestManagement.categoryTabs.all')}
            </TabsTrigger>
            {categories.map((category) => (
              <CategoryTab key={category.id} category={category} data-tab-measure={String(category.id)} />
            ))}
          </TabsList>
        </Tabs>
        <CategoryMoreButton hiddenCount={categories.length} tabIndex={-1} data-tab-measure={MEASURE_MORE} />
        <CategoryMoreButton hiddenCount={0} tabIndex={-1} data-tab-measure={MEASURE_MANAGE} />
      </div>
    </div>
  )
}

interface CategoryTabProps {
  category: RequestManagementProductCategory
  'data-tab-measure'?: string
}

function CategoryTab({ category, 'data-tab-measure': measureKey }: CategoryTabProps) {
  return (
    <TabsTrigger
      value={String(category.id)}
      className={FORM_TAB_TRIGGER_CLASS}
      title={category.name}
      data-tab-measure={measureKey}
    >
      <span className="max-w-48 truncate">{category.name}</span>
      <Badge variant="secondary" className="px-1.5 py-0 text-[0.65rem]">
        {category.requests_count}
      </Badge>
    </TabsTrigger>
  )
}
