import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import type { ProductLineLike } from '@/features/product-lines/types'

/**
 * Read-only list of confirmed funzione-aziendale + categoria-prodotto rows
 * (spec 0040 amendment rev.3, AC-101) — the SINGLE rendering shared by the
 * Opportunity record, the Offerta record and the user's competence
 * (user directive 2026-08-31: "cerchiamo di rendere tutto simile"). A null
 * `product_category` (spec 0129 D-3, users only — offers/opportunities never
 * carry one) renders as "all categories of the function". Falls back to the
 * kit's empty placeholder when there is no row.
 */
export function ProductLinesReadOnlyList({ lines }: { lines: ProductLineLike[] }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line) => (
        <li key={line.id}>
          <span className="font-medium">{line.business_function.name}</span>
          <span className="text-muted-foreground">
            {' '}
            — {line.product_category?.name ?? t('productLines.allCategoriesReadOnly')}
          </span>
        </li>
      ))}
    </ul>
  )
}
