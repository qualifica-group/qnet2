import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { CompetenceShareValue } from '@/features/product-lines/competence-share-meter'
import type { ProductLineLike } from '@/features/product-lines/types'

/**
 * The hierarchy label of a CARD row (spec 0132 D-5): "Root > Category", or
 * just "Category" when the category IS its own root — never duplicated.
 */
function categoryHierarchyLabel(line: ProductLineLike): string {
  const categoryName = line.product_category?.name ?? ''
  const root = line.root_category

  return root != null && root.id !== line.product_category?.id ? `${root.name} > ${categoryName}` : categoryName
}

/**
 * The i18n key of a competence row's category label: its category, or the
 * "all categories" wording, narrowed to the new or the legacy ones when the
 * row keeps only one of them (spec 0367).
 */
function allCategoriesKey(line: ProductLineLike): string {
  if (line.covers_legacy_categories === false) {
    return 'productLines.currentCategoriesOnlyReadOnly'
  }
  return line.covers_current_categories === false
    ? 'productLines.legacyCategoriesOnlyReadOnly'
    : 'productLines.allCategoriesReadOnly'
}

/**
 * Read-only list of confirmed product-lines rows (spec 0040 amendment rev.3,
 * AC-101; spec 0132 D-5 for the card format) — the SINGLE rendering shared by
 * every card record (opportunity, request, project, campaign, offer) and the
 * user's competence (user directive 2026-08-31: "cerchiamo di rendere tutto
 * simile"). The presence of `root_category` on a row decides the format:
 * - CARD row (has `root_category`): "Root > Category — funzione: Function".
 * - COMPETENCE row (no `root_category`, spec 0132 D-4, unchanged): "Function
 *   — Category", or "Function — all categories" for a null `product_category`
 *   (spec 0129 D-3), with the row's share as a mini bar and figure at the
 *   end of the line when the row carries one (spec 0346).
 * Falls back to the kit's empty placeholder when there is no row.
 */
export function ProductLinesReadOnlyList({ lines }: { lines: ProductLineLike[] }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line, index) =>
        line.root_category !== undefined ? (
          <li key={line.id} className="min-w-0">
            <span className="truncate font-medium" title={categoryHierarchyLabel(line)}>
              {categoryHierarchyLabel(line)}
            </span>
            <span className="text-muted-foreground">
              {' '}
              — {t('productLines.functionReadOnly', { name: line.business_function.name })}
            </span>
          </li>
        ) : (
          <li key={line.id} className="flex min-w-0 items-center gap-3">
            <span
              className="min-w-0 flex-1 truncate"
              title={`${line.business_function.name} — ${line.product_category?.name ?? t(allCategoriesKey(line))}`}
            >
              <span className="font-medium">{line.business_function.name}</span>
              <span className="text-muted-foreground">
                {' '}
                — {line.product_category?.name ?? t(allCategoriesKey(line))}
              </span>
            </span>
            {line.share_percentage !== undefined ? (
              <CompetenceShareValue share={line.share_percentage} index={index} />
            ) : null}
          </li>
        ),
      )}
    </ul>
  )
}
