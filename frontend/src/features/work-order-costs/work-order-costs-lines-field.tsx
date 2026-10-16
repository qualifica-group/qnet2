import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { QuoteLineCatalog } from '@/features/quotes/line-editor/quote-line-catalog'
import { QuoteLineEditorFrame, type QuoteLineEditorTotals } from '@/features/quotes/line-editor/quote-line-editor-frame'
import type { QuoteCatalogProduct, QuoteCatalogScope } from '@/features/quotes/line-editor/types'
import { MAX_LINES_PER_TAB } from '@/features/quotes/quote-schema'
import { fromCents, lineAmounts } from '@/lib/amounts/line-amounts'
import type { CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import { COST_QUANTITY_SCALE, WorkOrderCostCard, type RevenueLineOption } from '@/features/work-order-costs/work-order-cost-card'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

interface WorkOrderCostsLinesFieldProps {
  /** Stable RHF ids, one per row: they key the cards so typing never remounts them. */
  rowKeys: string[]
  rows: WorkOrderCostRowValues[]
  readOnly: boolean
  revenueOptions: RevenueLineOption[]
  rowErrors: (index: number) => CostRowErrors | undefined
  onChangeField: (index: number, patch: Partial<WorkOrderCostRowValues>) => void
  onAddProducts: (items: QuoteCatalogProduct[]) => void
  onRemove: (index: number) => void
}

/** Spec 0270 D-4: the whole COST catalogue, never scoped by category; a real cost may repeat a product. */
const WHOLE_CATALOGUE: QuoteCatalogScope = { kind: 'all' }
const NO_ADDED_IDS = new Set<number>()
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/** Sums the cards' live amounts in cents, the same rounding each card shows. */
function costTotals(rows: WorkOrderCostRowValues[]): Omit<QuoteLineEditorTotals, 'totalLabel'> {
  const cents = rows.reduce(
    (sum, row) => {
      const amounts = lineAmounts(row.quantity ?? 0, row.unit_price ?? 0, row.display.vat_percent, COST_QUANTITY_SCALE)
      return { net: sum.net + amounts.net, vat: sum.vat + amounts.vat, gross: sum.gross + amounts.total }
    },
    { net: 0, vat: 0, gross: 0 },
  )
  return { net: fromCents(cents.net), vat: fromCents(cents.vat), gross: fromCents(cents.gross) }
}

/**
 * "Costi effettivi" as the shared "catalogo + righe" block (spec 0270): the
 * COST catalogue beside the real-cost cards, one frame. Rows only come from
 * the catalogue; the save is still the tab's single replace of the whole set.
 */
export function WorkOrderCostsLinesField({
  rowKeys,
  rows,
  readOnly,
  revenueOptions,
  rowErrors,
  onChangeField,
  onAddProducts,
  onRemove,
}: WorkOrderCostsLinesFieldProps) {
  const { t } = useTranslation()
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE
  const totals = useMemo(() => costTotals(rows), [rows])

  return (
    <QuoteLineEditorFrame
      title={t('workOrders.costs.editor.rowsTitle')}
      count={rows.length}
      emptyText={t('workOrders.costs.editor.empty')}
      totals={{ ...totals, totalLabel: t('workOrders.costs.editor.totalLabel') }}
      catalog={
        readOnly ? null : (
          <QuoteLineCatalog
            variant="cost"
            scope={WHOLE_CATALOGUE}
            categoryTree={categoryTree}
            lockedHint=""
            singleSelect={false}
            remainingSlots={Math.max(0, MAX_LINES_PER_TAB - rows.length)}
            addedProductIds={NO_ADDED_IDS}
            onAdd={async (items) => {
              onAddProducts(items)
              return true
            }}
            onPickSingle={() => undefined}
            listClassName="max-h-96"
          />
        )
      }
    >
      {rows.map((row, index) => (
        <WorkOrderCostCard
          key={rowKeys[index]}
          index={index}
          row={row}
          disabled={readOnly}
          revenueOptions={revenueOptions}
          error={rowErrors(index)}
          onChangeField={(patch) => onChangeField(index, patch)}
          onRemove={() => onRemove(index)}
        />
      ))}
    </QuoteLineEditorFrame>
  )
}
