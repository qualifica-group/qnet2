import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/** The "Tutte" tab's Radix value; every category tab's value is its numeric id, stringified. */
const ALL_TAB_VALUE = 'all'

interface RequestManagementCategoryTabsProps {
  categories: RequestManagementProductCategory[]
  /** `null` selects "Tutte". */
  selectedCategoryId: number | null
  onSelect: (categoryId: number | null) => void
}

/**
 * Category tab strip above the Gestione Richieste grid (spec 0064): "Tutte"
 * always first and selected by default, then one tab per Product Category
 * actually present in the operator's visible requests, each carrying its own
 * request count. Presentation only — the parent owns persistence and derives
 * the table's scope from the selection (D-4: a scope change remounts the
 * whole table).
 *
 * Compact per ui-design.md §2 (`text-xs`, `px-2.5 py-1`, `size-3.5` icons);
 * the strip scrolls horizontally on its own (`overflow-x-auto`) instead of
 * ever widening the page at small viewports.
 */
export function RequestManagementCategoryTabs({
  categories,
  selectedCategoryId,
  onSelect,
}: RequestManagementCategoryTabsProps) {
  const { t } = useTranslation()
  const value = selectedCategoryId === null ? ALL_TAB_VALUE : String(selectedCategoryId)

  return (
    <div className="w-full overflow-x-auto">
      <Tabs
        value={value}
        onValueChange={(next) => onSelect(next === ALL_TAB_VALUE ? null : Number(next))}
      >
        <TabsList className={cn(FORM_TAB_LIST_CLASS, 'w-max flex-nowrap')}>
          <TabsTrigger value={ALL_TAB_VALUE} className={FORM_TAB_TRIGGER_CLASS}>
            {t('requestManagement.categoryTabs.all')}
          </TabsTrigger>
          {categories.map((category) => (
            <TabsTrigger key={category.id} value={String(category.id)} className={FORM_TAB_TRIGGER_CLASS}>
              {category.name}
              <Badge variant="secondary" className="px-1.5 py-0 text-[0.65rem]">
                {category.requests_count}
              </Badge>
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>
    </div>
  )
}
