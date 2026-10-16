import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { categoryPathLabel } from '@/features/product-lines/category-path-label'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { QuoteLine, QuoteLineVatRateRef } from '@/features/quotes/types'
import { OfferLineCard } from './offer-line-card'
import { OfferLinesCatalog } from './offer-lines-catalog'
import { useOfferLinesEditor } from './use-offer-lines-editor'

export interface OfferLinesEditorProps {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  /** Field not editable for the actor: rows read-only, no catalogue. */
  disabled: boolean
  /** The classification's categories: scope of the catalogue. */
  categoryIds: number[]
  categoryTree: ProductCategoryTreeNode[]
  maxRows: number
  singleCategoryMode: boolean
  simplified: boolean
  knownLines: QuoteLine[]
  knownVatRates: QuoteLineVatRateRef[]
  errors?: (QuoteLineRowErrors | undefined)[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /** Height cap of the catalogue list: the dialog grants it more room than a page section. */
  catalogListClassName?: string
}

/**
 * "Catalogo + righe" (spec 0268 D-1): the catalogue beside the picked rows
 * once the container is wide enough, stacked below them otherwise. Every
 * surface of the module (grid dialog, work panel, create form) mounts it
 * through `RequestOfferLinesField`.
 */
export function OfferLinesEditor({
  value,
  onChange,
  disabled,
  categoryIds,
  categoryTree,
  maxRows,
  singleCategoryMode,
  simplified,
  knownLines,
  knownVatRates,
  errors,
  vatRatePercentFor,
  rememberVatRatePercent,
  catalogListClassName = 'max-h-96',
}: OfferLinesEditorProps) {
  const { t } = useTranslation()
  const editor = useOfferLinesEditor({
    value,
    onChange,
    knownLines,
    maxRows,
    simplified,
    vatRatePercentFor,
    rememberVatRatePercent,
  })
  const vatRateById = (id: number | null) => (id === null ? undefined : knownVatRates.find((rate) => rate.id === id))
  const pathOf = (categoryId: number | null | undefined) =>
    categoryId === null || categoryId === undefined ? null : categoryPathLabel(categoryTree, categoryId)

  return (
    <div className="@container">
      <div className={cn('grid gap-3', !disabled && '@3xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]')}>
        <section
          aria-label={t('requestManagement.offerLines.rows.title')}
          // The twin of the catalogue pane: the same `bg-surface` panel, so
          // the row cards (`bg-card`) stand one rung above it on every surface.
          className="flex min-w-0 flex-col rounded-lg border bg-surface @3xl:order-2"
        >
          <p className="border-b p-3 text-sm font-semibold">
            {t('requestManagement.offerLines.rows.title')}
            <span className="ml-1.5 font-normal text-muted-foreground">({editor.visibleLines.length})</span>
          </p>

          {editor.visibleLines.length === 0 ? (
            <p className="m-2 rounded-lg border border-dashed bg-card px-3 py-6 text-center text-xs text-muted-foreground">
              {t('requestManagement.offerLines.rows.empty')}
            </p>
          ) : (
            <ul className="flex flex-col gap-2 p-2">
              {editor.visibleLines.map(({ row, index }, position) => {
                const product = row.product_id === null ? undefined : editor.productViews.get(row.product_id)
                return (
                  // The row's identity IS its form position (mirrors `QuoteLinesField`).
                  <OfferLineCard
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
                    onRemove={() => editor.removeRow(index)}
                  />
                )
              })}
            </ul>
          )}

          {editor.visibleLines.length > 0 ? (
            <dl className="mt-auto flex flex-wrap items-baseline justify-end gap-x-4 gap-y-1 border-t px-3 py-2.5 text-xs">
              <div className="flex gap-1.5">
                <dt className="text-muted-foreground">{t('quotes.form.lineNetHeader')}</dt>
                <dd className="tabular-nums">{formatQuoteAmount(editor.totals.net)}</dd>
              </div>
              <div className="flex gap-1.5">
                <dt className="text-muted-foreground">{t('quotes.form.lineVatHeader')}</dt>
                <dd className="tabular-nums">{formatQuoteAmount(editor.totals.vat)}</dd>
              </div>
              <div className="flex gap-1.5 text-sm">
                <dt className="font-medium">{t('requestManagement.offerLines.rows.offerTotal')}</dt>
                <dd className="font-semibold tabular-nums">{formatQuoteAmount(editor.totals.gross)}</dd>
              </div>
            </dl>
          ) : null}
        </section>

        {disabled ? null : (
          <div className="flex min-w-0 flex-col @3xl:order-1">
            <OfferLinesCatalog
              categoryIds={categoryIds}
              categoryTree={categoryTree}
              singleSelect={singleCategoryMode}
              remainingSlots={editor.remainingSlots}
              addedProductIds={editor.addedProductIds}
              onAdd={editor.addProducts}
              onPickSingle={editor.pickSingle}
              listClassName={catalogListClassName}
            />
          </div>
        )}
      </div>
    </div>
  )
}
