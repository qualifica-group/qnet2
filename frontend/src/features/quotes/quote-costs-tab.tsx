import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import type { TFunction } from 'i18next'
import { MetaField } from '@/features/authorization/MetaField'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { QuoteLineEditor } from '@/features/quotes/line-editor/quote-line-editor'
import type { QuoteCatalogScope } from '@/features/quotes/line-editor/types'
import { QuoteCostAssociationSelect } from '@/features/quotes/quote-cost-association-select'
import type { QuoteCostOfferLineOption, QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import { sanitizeCostOfferLineKeys } from '@/features/quotes/quote-line-values'
import { MAX_LINES_PER_TAB, type QuoteFormValues, type QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

interface QuoteCostsTabProps {
  control: Control<QuoteFormValues>
  errors?: (QuoteLineRowErrors | undefined)[]
  /** The persisted `cost_lines` (edit mode only, `[]` on create), for label hydration. */
  knownLines: QuoteLine[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /** Spec 0144: resolves an OFFER row's product name for the "Associated product" options. */
  productNameFor?: (productId: number) => string | null
}

/** Hoisted: an inline function default would be a new reference on every render. */
const NO_PRODUCT_NAME = (): string | null => null
const EMPTY_TREE: ProductCategoryTreeNode[] = []
/** Spec 0065 AC-073: cost rows have no category scope at all. */
const WHOLE_CATALOGUE: QuoteCatalogScope = { kind: 'all' }

/**
 * One selectable entry per OFFER row (spec 0144 AC-011): only rows that
 * already carry a product are offerable — a pristine row is not yet a real
 * "riga prodotto" to attribute a cost to.
 */
function buildOfferLineOptions(
  offerLines: QuoteLineFormValues[],
  productNameFor: (productId: number) => string | null,
  t: TFunction,
): QuoteCostOfferLineOption[] {
  return offerLines.reduce<QuoteCostOfferLineOption[]>((options, row, index) => {
    if (row.product_id === null || !row.client_key) {
      return options
    }
    options.push({
      key: row.client_key,
      label: t('quotes.form.costsTab.associatedProductOption', {
        product: productNameFor(row.product_id) ?? t('quotes.form.commissions.productFallback'),
        n: index + 1,
      }),
    })
    return options
  }, [])
}

/**
 * Cost lines tab (spec 0065 AC-073, catalogue editor since spec 0269): the
 * whole COST catalogue, no category scope — cost rows have no effect on the
 * opportunity's product lines (D-7) — and a product may be added more than
 * once (D-3). Each card carries the "Prodotto associato" field, whose options
 * are the live OFFER rows (`useWatch`), so a product picked in THIS session
 * is offerable before the offer is even saved.
 */
export function QuoteCostsTab({
  control,
  errors,
  knownLines,
  vatRatePercentFor,
  rememberVatRatePercent,
  productNameFor = NO_PRODUCT_NAME,
}: QuoteCostsTabProps) {
  const { t } = useTranslation()
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE

  const offerLines = useWatch({ control, name: 'offer_lines' })
  const offerLineOptions = useMemo(
    () => buildOfferLineOptions(offerLines, productNameFor, t),
    [offerLines, productNameFor, t],
  )
  const validOfferLineKeys = useMemo(() => new Set(offerLineOptions.map((option) => option.key)), [offerLineOptions])

  return (
    // The editor's rows pane already titles the collection: the field label
    // stays for assistive tech only.
    <MetaFieldRowContext.Provider value="cost_lines">
      <MetaField control={control} name="cost_lines" metaKey="cost_lines" label={t('quotes.form.costsTab.fieldLabel')}>
        {({ field, disabled }) => {
          const rows = sanitizeCostOfferLineKeys(field.value, validOfferLineKeys)
          const patchRow = (index: number, patch: Partial<QuoteLineFormValues>) =>
            field.onChange(rows.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))

          return (
            <QuoteLineEditor
              value={rows}
              onChange={field.onChange}
              variant="cost"
              disabled={disabled}
              catalogScope={WHOLE_CATALOGUE}
              categoryTree={categoryTree}
              maxRows={MAX_LINES_PER_TAB}
              singleCategoryMode={false}
              allowDuplicates
              knownLines={knownLines}
              errors={errors}
              vatRatePercentFor={vatRatePercentFor}
              rememberVatRatePercent={rememberVatRatePercent}
              renderRowSlots={(row, index, position) => ({
                extraField: (
                  <QuoteCostAssociationSelect
                    position={position}
                    value={row.offer_line_key}
                    options={offerLineOptions}
                    disabled={disabled}
                    onChange={(offerLineKey) => patchRow(index, { offer_line_key: offerLineKey })}
                  />
                ),
              })}
            />
          )
        }}
      </MetaField>
    </MetaFieldRowContext.Provider>
  )
}
