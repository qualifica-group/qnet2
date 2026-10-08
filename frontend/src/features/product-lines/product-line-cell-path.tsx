import { useTranslation } from 'react-i18next'
import { ChevronRight, ListFilter, Plus, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { CellPickStep, PickedCategory } from '@/features/product-lines/use-product-line-cell-picker'

interface ProductLineCellPathProps {
  step: CellPickStep
  rootCategory: PickedCategory | null
  filterCategory: PickedCategory | null
  /** The root has grouping children and no filter is applied yet. */
  canFilter: boolean
  onOpenFilter: () => void
  onClearFilter: () => void
}

/**
 * The breadcrumb above `ProductLinesCellEditor`'s list: what is already picked
 * (root, then the optional intermediate filter as a removable chip) followed
 * by the step being picked now, muted. Before a root is chosen it is the step
 * hint alone. The "Filtro" affordance sits where the filter would go, dashed
 * like every opt-in step of the product lines.
 */
export function ProductLineCellPath({
  step,
  rootCategory,
  filterCategory,
  canFilter,
  onOpenFilter,
  onClearFilter,
}: ProductLineCellPathProps) {
  const { t } = useTranslation()

  if (step === 'root_category' || rootCategory === null) {
    return <p className="px-2 pb-1 text-xs text-muted-foreground">{t('table.productLinesEditor.rootCategoryStep')}</p>
  }

  return (
    <nav
      aria-label={t('table.productLinesEditor.path')}
      className="flex min-w-0 flex-wrap items-center gap-1 px-2 pb-1.5 text-xs"
    >
      <span className="max-w-full truncate font-medium" title={rootCategory.name}>
        {rootCategory.name}
      </span>
      <PathSeparator />

      {filterCategory !== null ? (
        <>
          <Badge variant="outline" className="max-w-40 bg-card pr-0.5 font-normal">
            <ListFilter aria-hidden="true" className="text-muted-foreground" />
            <span className="truncate" title={filterCategory.name}>
              {filterCategory.name}
            </span>
            {/* Same inline clear as the select triggers' own. */}
            <button
              type="button"
              aria-label={t('table.productLinesEditor.clearFilter', { name: filterCategory.name })}
              onClick={onClearFilter}
              className="rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
            >
              <X aria-hidden="true" className="size-3" />
            </button>
          </Badge>
          <PathSeparator />
        </>
      ) : canFilter ? (
        <>
          <Button
            type="button"
            variant="outline"
            size="xs"
            onClick={onOpenFilter}
            className="border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
          >
            <Plus aria-hidden="true" />
            {t('productLines.addFilter')}
          </Button>
          <PathSeparator />
        </>
      ) : null}

      <span className="text-muted-foreground" aria-current="step">
        {step === 'filter_category' ? t('table.productLinesEditor.filterStep') : t('table.productLinesEditor.categoryStep')}
      </span>
    </nav>
  )
}

function PathSeparator() {
  return <ChevronRight aria-hidden="true" className="size-3 shrink-0 text-muted-foreground" />
}
