import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { TrendingDown } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { QuoteLinesField, knownProductsFrom, knownVatRatesFrom } from '@/features/quotes/quote-lines-field'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

interface QuoteCostsTabProps {
  control: Control<QuoteFormValues>
  errors?: (QuoteLineRowErrors | undefined)[]
  /** The persisted `cost_lines` (edit mode only, `[]` on create), for label hydration. */
  knownLines: QuoteLine[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/**
 * Cost lines tab (spec 0065 AC-073): the product picker NEVER sends
 * `category_ids` — unlike `QuoteOfferTab`, cost rows have no effect on the
 * opportunity's product lines (D-7) and no default scope to offer.
 */
export function QuoteCostsTab({
  control,
  errors,
  knownLines,
  vatRatePercentFor,
  rememberVatRatePercent,
}: QuoteCostsTabProps) {
  const { t } = useTranslation()

  const knownProducts = useMemo(() => knownProductsFrom(knownLines), [knownLines])
  const knownVatRates = useMemo(() => knownVatRatesFrom(knownLines), [knownLines])

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
            value={field.value}
            onChange={field.onChange}
            variant="cost"
            disabled={disabled}
            categoryIds={undefined}
            errors={errors}
            knownProducts={knownProducts}
            knownVatRates={knownVatRates}
            vatRatePercentFor={vatRatePercentFor}
            rememberVatRatePercent={rememberVatRatePercent}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
