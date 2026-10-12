import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { Badge } from '@/components/ui/badge'
import { resolveManagerLabels } from '@/features/product-categories/resolve-manager-labels'
import type { ProductCategoryDetail } from '@/features/product-categories/types'

/** The resolved G.A. denominations, one line per level: the persisted value of the manager labels block row. */
export function ProductCategoryManagerLabelsOverview({ category }: { category: ProductCategoryDetail }) {
  const { t } = useTranslation()
  const labels = resolveManagerLabels(category)

  if (labels.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-1.5">
      {labels.map(({ position, label, inherited }) => (
        <li key={position} className="flex flex-wrap items-center gap-2 text-sm">
          <span className="w-16 shrink-0 text-xs font-medium text-muted-foreground">
            {t('productCategories.form.managerLabelLevel', { n: position })}
          </span>
          <span>{label}</span>
          {inherited && (
            <Badge variant="outline" className="text-xs">
              {t('productCategories.detail.managerLabelInherited')}
            </Badge>
          )}
        </li>
      ))}
    </ul>
  )
}
