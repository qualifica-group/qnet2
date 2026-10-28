import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine, QuoteLineVatRateRef } from '@/features/quotes/types'
import type { QuoteLineVariant } from '@/features/quotes/use-quote-lines-field'
import { QuoteLineCard } from './quote-line-card'
import { QuoteLineCatalog } from './quote-line-catalog'
import { QuoteLineEditorFrame } from './quote-line-editor-frame'
import type {
  QuoteCatalogProduct,
  QuoteCatalogScope,
  QuoteLineCardSlots,
  QuoteLineCommissionsAdapter,
  QuoteLineProductView,
} from './types'
import { useQuoteLineEditor } from './use-quote-line-editor'

/** The VAT rates the persisted rows carry, deduped by id: the label hydration of a row's VAT picker. */
function knownVatRatesFrom(lines: QuoteLine[]): QuoteLineVatRateRef[] {
  const byId = new Map<number, QuoteLineVatRateRef>()
  for (const line of lines) {
    if (line.vat_rate) {
      byId.set(line.vat_rate.id, line.vat_rate)
    }
  }
  return [...byId.values()]
}

export interface QuoteLineEditorProps {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  variant: QuoteLineVariant
  /** Field not editable for the actor: rows read-only, no catalogue. */
  disabled: boolean
  catalogScope: QuoteCatalogScope
  /** Shown in the catalogue while its category scope is empty. */
  lockedHint?: string
  categoryTree: ProductCategoryTreeNode[]
  maxRows: number
  singleCategoryMode: boolean
  simplified?: boolean
  /** Spec 0269 D-3: cost rows may repeat a product. */
  allowDuplicates?: boolean
  knownLines: QuoteLine[]
  errors?: (QuoteLineRowErrors | undefined)[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  commissions?: QuoteLineCommissionsAdapter
  onProductsPicked?: (items: QuoteCatalogProduct[]) => void
  /** A channel's own extras on each card; `position` is the row's 1-based number. */
  renderRowSlots?: (
    row: QuoteLineFormValues,
    index: number,
    position: number,
    product: QuoteLineProductView | undefined,
  ) => QuoteLineCardSlots
  /** Height cap of the catalogue list: the dialog grants it more room than a page section. */
  catalogListClassName?: string
}

/**
 * "Catalogo + righe" (spec 0268 D-1, shared by spec 0269): the catalogue
 * beside the picked rows once the container is wide enough, stacked below
 * them otherwise. Mounted by Gestione Richieste/Iscritti
 * (`RequestOfferLinesField`) and by both tabs of the Offerte form.
 */
export function QuoteLineEditor({
  value,
  onChange,
  variant,
  disabled,
  catalogScope,
  lockedHint = '',
  categoryTree,
  maxRows,
  singleCategoryMode,
  simplified = false,
  allowDuplicates = false,
  knownLines,
  errors,
  vatRatePercentFor,
  rememberVatRatePercent,
  commissions,
  onProductsPicked,
  renderRowSlots,
  catalogListClassName = 'max-h-96',
}: QuoteLineEditorProps) {
  const { t } = useTranslation()
  const editor = useQuoteLineEditor({
    value,
    onChange,
    variant,
    knownLines,
    maxRows,
    simplified,
    allowDuplicates,
    commissions,
    onProductsPicked,
    vatRatePercentFor,
    rememberVatRatePercent,
  })
  const knownVatRates = useMemo(() => knownVatRatesFrom(knownLines), [knownLines])
  const vatRateById = (id: number | null) => (id === null ? undefined : knownVatRates.find((rate) => rate.id === id))
  const pathOf = (categoryId: number | null | undefined) =>
    categoryId === null || categoryId === undefined ? null : categoryPathLabel(categoryTree, categoryId)
  const title = t(variant === 'revenue' ? 'quotes.lineEditor.rows.titleRevenue' : 'quotes.lineEditor.rows.titleCost')

  return (
    <QuoteLineEditorFrame
      title={title}
      count={editor.visibleLines.length}
      emptyText={t('quotes.lineEditor.rows.empty')}
      totals={{
        ...editor.totals,
        totalLabel: t(variant === 'revenue' ? 'quotes.lineEditor.rows.totalRevenue' : 'quotes.lineEditor.rows.totalCost'),
      }}
      catalog={
        disabled ? null : (
          <QuoteLineCatalog
            variant={variant}
            scope={catalogScope}
            categoryTree={categoryTree}
            lockedHint={lockedHint}
            singleSelect={singleCategoryMode}
            remainingSlots={editor.remainingSlots}
            addedProductIds={editor.addedProductIds}
            onAdd={editor.addProducts}
            onPickSingle={(item) => void editor.pickSingle(item)}
            listClassName={catalogListClassName}
          />
        )
      }
    >
      {editor.visibleLines.map(({ row, index }, position) => {
        const product = row.product_id === null ? undefined : editor.productViews.get(row.product_id)
        return (
          // The row's identity IS its form position.
          <QuoteLineCard
            key={index}
            position={position + 1}
            row={row}
            product={product}
            categoryPath={pathOf(product?.categoryId)}
            knownVatRate={vatRateById(row.vat_rate_id)}
            disabled={disabled}
            simplified={simplified}
            error={errors?.[index]}
            vatRatePercentFor={vatRatePercentFor}
            rememberVatRatePercent={rememberVatRatePercent}
            onChangeField={(patch) => editor.setField(index, patch)}
            suppliers={editor.suppliersOf(row)}
            supplierAmount={variant === 'revenue' ? 'price' : 'cost'}
            onChangeSupplier={(option) => void editor.changeSupplier(index, option)}
            onRemove={() => editor.removeRow(index)}
            slots={renderRowSlots?.(row, index, position + 1, product)}
          />
        )
      })}
    </QuoteLineEditorFrame>
  )
}
