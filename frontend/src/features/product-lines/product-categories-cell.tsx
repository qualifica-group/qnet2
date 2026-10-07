import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { EmptyCell } from '@/features/table/cell-renderers'
import type { ProductLineCellValue } from '@/features/product-lines/product-lines-cell-editor'

/** The pair's "Root > Category" hierarchy (spec 0132 D-5): never repeated when the category IS its own root. */
function categoryHierarchyLabel(pair: ProductLineCellValue): string {
  return pair.root_category_id !== pair.product_category_id
    ? `${pair.root_category_name} > ${pair.product_category_name}`
    : pair.product_category_name
}

/**
 * The pair's tooltip line (spec 0132 D-5): "Root > Category — funzione:
 * Function" — the same format `ProductLinesReadOnlyList` renders in the card
 * detail. `business_function_name` is missing for a pair just picked in the
 * inline editor and not yet round-tripped from the server (it is never
 * guessed client-side, see `product-lines-cell-editor.tsx`): the function
 * segment is omitted rather than invented.
 */
function pairTooltipLine(pair: ProductLineCellValue, t: TFunction): string {
  const hierarchy = categoryHierarchyLabel(pair)
  return pair.business_function_name === undefined
    ? hierarchy
    : `${hierarchy} — ${t('productLines.functionReadOnly', { name: pair.business_function_name })}`
}

/**
 * The "Categoria prodotto" cell (spec 0075, tooltip reshaped by spec 0132
 * D-5; shared with the Opportunita' grid by spec 0206 D-9): the row projects
 * the record's own product-category pairs — what the `product_lines` inline
 * editor commits — so the cell renders the CATEGORY names out of them,
 * comma-joined, with the full "root > category — funzione" pair list as its
 * native tooltip.
 */
export function ProductCategoriesCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const pairs = Array.isArray(value) ? (value as ProductLineCellValue[]) : []

  if (pairs.length === 0) {
    return <EmptyCell align="left" />
  }

  const label = pairs.map((pair) => pair.product_category_name).join(', ')
  const tooltip = pairs.map((pair) => pairTooltipLine(pair, t)).join('\n')

  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={tooltip}>
        {label}
      </span>
    </div>
  )
}
