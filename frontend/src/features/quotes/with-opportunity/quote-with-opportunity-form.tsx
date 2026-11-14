import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { RecordCanvas } from '@/components/detail/record-panel'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchQuoteNextCode } from '@/features/quotes/api'
import { useQuoteWithOpportunity } from '@/features/quotes/with-opportunity/use-quote-with-opportunity'
import { WithOpportunityActions, WithOpportunityTabs } from '@/features/quotes/with-opportunity/with-opportunity-nav'
import { OpportunityStep, QuoteStep } from '@/features/quotes/with-opportunity/with-opportunity-steps'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteDetail } from '@/features/quotes/types'

interface QuoteWithOpportunityFormProps {
  /** Called with the created offer so the host opens its detail. */
  onSuccess: (quote: QuoteDetail) => void
  onCancel: () => void
}

/**
 * The "Nuova opportunita' + offerta" procedure (spec 0396): resolves BOTH
 * create-context metas (`opportunities` for step 1, `quotes` for step 2) and
 * the offer's `next-code` suggestion (uncached, like `QuoteForm`) before
 * rendering, then hands off to the body.
 */
export function QuoteWithOpportunityForm({ onSuccess, onCancel }: QuoteWithOpportunityFormProps) {
  const { t } = useTranslation()
  const opportunityMeta = useResourceMeta('opportunities', true)
  const quoteMeta = useResourceMeta('quotes', true)
  const nextCode = useQuery({
    queryKey: ['quotes', 'next-code'],
    queryFn: fetchQuoteNextCode,
    staleTime: 0,
    gcTime: 0,
  })

  if (opportunityMeta.isPending || quoteMeta.isPending || nextCode.isLoading) {
    return <RecordFormSkeleton />
  }

  if (opportunityMeta.isError || quoteMeta.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button
          variant="outline"
          size="sm"
          className="bg-card"
          onClick={() => void Promise.all([opportunityMeta.refetch(), quoteMeta.refetch()])}
        >
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <WithOpportunityBody
      initialCode={nextCode.data ?? ''}
      opportunityPermissions={opportunityMeta.data?.permissions ?? null}
      quotePermissions={quoteMeta.data?.permissions ?? null}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

interface WithOpportunityBodyProps extends QuoteWithOpportunityFormProps {
  initialCode: string
  opportunityPermissions: ResourcePermissions | null
  quotePermissions: ResourcePermissions | null
}

/** Pure composition: the tab strip, the active step and the actions (top and foot); the logic lives in `useQuoteWithOpportunity`. */
function WithOpportunityBody({
  initialCode,
  opportunityPermissions,
  quotePermissions,
  onSuccess,
  onCancel,
}: WithOpportunityBodyProps) {
  const { t } = useTranslation()
  const wizard = useQuoteWithOpportunity({ initialCode, onSuccess })
  const { step, goNext, goBack, isChecking, serverError } = wizard
  const isSaving = wizard.quoteForm.form.formState.isSubmitting
  const actions = (
    <WithOpportunityActions
      step={step}
      isChecking={isChecking}
      isSaving={isSaving}
      onBack={goBack}
      onCancel={onCancel}
    />
  )

  return (
    <RecordCanvas>
      <RecordFormBody>
        <h2 className="text-base font-semibold text-foreground">{t('quotes.withOpportunity.chooser.new.title')}</h2>

        <div className="flex flex-wrap items-center justify-between gap-2">
          <WithOpportunityTabs step={step} onNext={() => void goNext()} onBack={goBack} />
          {actions}
        </div>

        <p className="text-xs text-muted-foreground">
          {step === 'opportunity' ? t('quotes.withOpportunity.hint.opportunity') : t('quotes.withOpportunity.hint.quote')}
        </p>

        {serverError ? (
          <p
            role="alert"
            className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
          >
            {serverError}
          </p>
        ) : null}

        {step === 'opportunity' ? (
          <OpportunityStep wizard={wizard} permissions={opportunityPermissions} />
        ) : (
          <QuoteStep wizard={wizard} permissions={quotePermissions} />
        )}

        <div className="border-t pt-4">{actions}</div>
      </RecordFormBody>
    </RecordCanvas>
  )
}
