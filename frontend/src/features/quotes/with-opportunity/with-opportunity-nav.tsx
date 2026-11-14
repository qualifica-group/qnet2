import { useTranslation } from 'react-i18next'
import { ArrowLeft, ArrowRight, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { WithOpportunityStep } from '@/features/quotes/with-opportunity/use-quote-with-opportunity'

/** DOM ids bridging the nav's actions to each step's `<form>` via the HTML `form=` attribute. */
export const OPPORTUNITY_STEP_FORM_ID = 'quote-with-opportunity-opportunity-form'
export const QUOTE_STEP_FORM_ID = 'quote-with-opportunity-quote-form'

interface WithOpportunityTabsProps {
  step: WithOpportunityStep
  /** Asking for the offer step runs the same gate as "Avanti". */
  onNext: () => void
  onBack: () => void
}

/** The two-step strip: 1 Opportunita', 2 Offerta. The second tab is only reachable through the step-1 gate. */
export function WithOpportunityTabs({ step, onNext, onBack }: WithOpportunityTabsProps) {
  const { t } = useTranslation()

  return (
    <Tabs value={step} onValueChange={(value) => (value === 'quote' ? onNext() : onBack())}>
      <TabsList aria-label={t('quotes.withOpportunity.tabsLabel')} className="w-auto">
        <TabsTrigger value="opportunity">
          <span aria-hidden="true">1</span>
          {t('quotes.withOpportunity.tabs.opportunity')}
        </TabsTrigger>
        <TabsTrigger value="quote">
          <span aria-hidden="true">2</span>
          {t('quotes.withOpportunity.tabs.quote')}
        </TabsTrigger>
      </TabsList>
    </Tabs>
  )
}

interface WithOpportunityActionsProps {
  step: WithOpportunityStep
  isChecking: boolean
  isSaving: boolean
  onBack: () => void
  onCancel: () => void
}

/** Annulla / Indietro / Avanti (step 1) or Salva (step 2): rendered at the top and repeated at the foot of the form. */
export function WithOpportunityActions({
  step,
  isChecking,
  isSaving,
  onBack,
  onCancel,
}: WithOpportunityActionsProps) {
  const { t } = useTranslation()
  const isBusy = isChecking || isSaving

  return (
    <div className="flex flex-wrap items-center justify-end gap-2">
      <Button type="button" variant="secondary" onClick={onCancel} disabled={isBusy}>
        {t('quotes.form.cancel')}
      </Button>
      {step === 'quote' ? (
        <>
          <Button type="button" variant="secondary" onClick={onBack} disabled={isBusy}>
            <ArrowLeft aria-hidden="true" />
            {t('quotes.withOpportunity.back')}
          </Button>
          <Button type="submit" form={QUOTE_STEP_FORM_ID} disabled={isBusy}>
            {isSaving ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
            {isSaving ? t('quotes.form.saving') : t('quotes.form.save')}
          </Button>
        </>
      ) : (
        <Button type="submit" form={OPPORTUNITY_STEP_FORM_ID} disabled={isBusy}>
          {isChecking ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {isChecking ? t('quotes.withOpportunity.checking') : t('quotes.withOpportunity.next')}
          {isChecking ? null : <ArrowRight aria-hidden="true" />}
        </Button>
      )}
    </div>
  )
}
