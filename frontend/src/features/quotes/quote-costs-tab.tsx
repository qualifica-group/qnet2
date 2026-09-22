import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import type { TFunction } from 'i18next'
import { TrendingDown } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { QuoteLinesField, knownProductsFrom, knownVatRatesFrom } from '@/features/quotes/quote-lines-field'
import { sanitizeCostOfferLineKeys } from '@/features/quotes/quote-line-values'
import type { QuoteCostOfferLineOption, QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

interface QuoteCostsTabProps {
  control: Control<QuoteFormValues>
  errors?: (QuoteLineRowErrors | undefined)[]
  /** The persisted `cost_lines` (edit mode only, `[]` on create), for label hydration. */
  knownLines: QuoteLine[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  /** Spec 0144: resolves an OFFER row's product name for the "Associated product" column's options. */
  productNameFor?: (productId: number) => string | null
}

/** Hoisted: an inline `[]`/function default would be a new reference on every render. */
const NO_OFFER_LINE_OPTIONS: QuoteCostOfferLineOption[] = []
const NO_PRODUCT_NAME = (): string | null => null

/**
 * One selectable entry per OFFER row (spec 0065 AC-011): only rows that
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
 * Cost lines tab (spec 0065 AC-073): the product picker NEVER sends
 * `category_ids` — unlike `QuoteOfferTab`, cost rows have no effect on the
 * opportunity's product lines (D-7) and no default scope to offer.
 *
 * Spec 0144 (D-1/AC-011): each row also carries the "Associated product"
 * column, whose options are the live OFFER rows (`useWatch`), so a product
 * picked in THIS session is offerable before the offer is even saved.
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

  const knownProducts = useMemo(() => knownProductsFrom(knownLines), [knownLines])
  const knownVatRates = useMemo(() => knownVatRatesFrom(knownLines), [knownLines])

  const offerLines = useWatch({ control, name: 'offer_lines' })
  const offerLineOptions = useMemo(
    () => buildOfferLineOptions(offerLines, productNameFor, t),
    [offerLines, productNameFor, t],
  )
  const validOfferLineKeys = useMemo(
    () => new Set(offerLineOptions.map((option) => option.key)),
    [offerLineOptions],
  )

  return (
    <FormSection
      icon={TrendingDown}
      title={t('quotes.form.sections.costs.title')}
      description={t('quotes.form.sections.costs.description')}
    >
      <MetaField
        control={control}
        name="cost_lines"
        metaKey="cost_lines"
        label={t('quotes.form.costsTab.fieldLabel')}
      >
        {({ field, disabled }) => (
          <QuoteLinesField
            value={sanitizeCostOfferLineKeys(field.value, validOfferLineKeys)}
            onChange={field.onChange}
            variant="cost"
            disabled={disabled}
            categoryIds={undefined}
            errors={errors}
            knownProducts={knownProducts}
            knownVatRates={knownVatRates}
            vatRatePercentFor={vatRatePercentFor}
            rememberVatRatePercent={rememberVatRatePercent}
            offerLineOptions={offerLineOptions.length > 0 ? offerLineOptions : NO_OFFER_LINE_OPTIONS}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
