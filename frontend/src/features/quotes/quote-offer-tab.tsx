import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useWatch, type Control } from 'react-hook-form'
import { Lock, LockOpen, TrendingUp } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { FormSection } from '@/components/form-section'
import { useConfirm } from '@/components/confirm-dialog-context'
import { MetaField } from '@/features/authorization/MetaField'
import { fetchOpportunity, opportunityDetailQueryKey } from '@/features/opportunities/api'
import { QuoteLinesField, knownProductsFrom, knownVatRatesFrom } from '@/features/quotes/quote-lines-field'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

interface QuoteOfferTabProps {
  control: Control<QuoteFormValues>
  errors?: (QuoteLineRowErrors | undefined)[]
  /** The persisted `offer_lines` (edit mode only, `[]` on create), for label hydration. */
  knownLines: QuoteLine[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  quoteId?: number
}

/**
 * Offer (revenue) lines tab (spec 0065 AC-072): the product picker defaults
 * to the SELECTED opportunity's own product-line categories, refetched
 * whenever `opportunity_id` changes (`fetchOpportunity`, already exported by
 * `features/opportunities/api.ts` — no new opportunities-side file). An
 * explicit unlock, confirmed via `useConfirm()`, drops the filter for every
 * row in this tab; `QuoteCostsTab` never carries it at all (AC-073). This is
 * the LAST picker that offers it: a cross-category quote line still widens the
 * opportunity's coverage (OpportunityProductLineCoverage), unlike the products
 * of interest since the user directive 2026-08-05.
 */
export function QuoteOfferTab({
  control,
  errors,
  knownLines,
  vatRatePercentFor,
  rememberVatRatePercent,
  quoteId,
}: QuoteOfferTabProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [unlocked, setUnlocked] = useState(false)

  const opportunityId = useWatch({ control, name: 'opportunity_id' })
  const commercialId = useWatch({ control, name: 'commercial_id' })
  const reporterId = useWatch({ control, name: 'reporter_id' })
  const supervisorId = useWatch({ control, name: 'supervisor_id' })

  const opportunityQuery = useQuery({
    queryKey: opportunityId !== null ? opportunityDetailQueryKey(opportunityId) : ['opportunities', 'detail', null],
    queryFn: () => fetchOpportunity(opportunityId as number),
    enabled: opportunityId !== null,
    staleTime: 5 * 60 * 1000,
  })

  const scopedCategoryIds = useMemo(() => {
    if (!opportunityQuery.data) {
      return []
    }
    return [...new Set(opportunityQuery.data.product_lines.map((line) => line.product_category.id))]
  }, [opportunityQuery.data])

  const categoryIds = unlocked ? undefined : scopedCategoryIds
  const lockedWithoutScope = !unlocked && scopedCategoryIds.length === 0

  const knownProducts = useMemo(() => knownProductsFrom(knownLines), [knownLines])
  const knownVatRates = useMemo(() => knownVatRatesFrom(knownLines), [knownLines])

  const requestUnlock = async () => {
    const confirmed = await confirm({
      tone: 'warning',
      title: t('quotes.form.offerTab.unlockDialog.title'),
      description: t('quotes.form.offerTab.unlockDialog.description'),
      confirmLabel: t('quotes.form.offerTab.unlockDialog.confirm'),
      cancelLabel: t('common.cancel'),
    })
    if (confirmed) {
      setUnlocked(true)
    }
  }

  return (
    <FormSection
      icon={TrendingUp}
      title={t('quotes.form.sections.offer.title')}
      description={t('quotes.form.sections.offer.description')}
    >
      <MetaField
        control={control}
        name="offer_lines"
        metaKey="offer_lines"
        label={t('quotes.form.offerTab.fieldLabel')}
      >
        {({ field, disabled }) => (
          <div className="flex flex-col gap-2">
            <QuoteLinesField
              value={field.value}
              onChange={field.onChange}
              variant="revenue"
              disabled={disabled}
              categoryIds={categoryIds}
              errors={errors}
              knownProducts={knownProducts}
              knownVatRates={knownVatRates}
              vatRatePercentFor={vatRatePercentFor}
              rememberVatRatePercent={rememberVatRatePercent}
              commissionContext={{ quoteId, commercialId, reporterId, supervisorId }}
            />

            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-xs text-muted-foreground">
                {opportunityId === null
                  ? t('quotes.form.offerTab.hintNoOpportunity')
                  : unlocked
                    ? t('quotes.form.offerTab.hintUnlocked')
                    : lockedWithoutScope
                      ? t('quotes.form.offerTab.hintNoCategories')
                      : t('quotes.form.offerTab.hintScoped')}
              </p>

              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={disabled}
                onClick={unlocked ? () => setUnlocked(false) : requestUnlock}
              >
                {unlocked ? (
                  <>
                    <Lock aria-hidden="true" className="size-3.5" />
                    {t('quotes.form.offerTab.relock')}
                  </>
                ) : (
                  <>
                    <LockOpen aria-hidden="true" className="size-3.5" />
                    {t('quotes.form.offerTab.unlock')}
                  </>
                )}
              </Button>
            </div>
          </div>
        )}
      </MetaField>
    </FormSection>
  )
}
