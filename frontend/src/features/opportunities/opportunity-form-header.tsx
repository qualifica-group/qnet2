import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Handshake, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { OpportunityStatsStrip } from '@/features/opportunities/opportunity-detail-header'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityFormHeaderProps {
  control: Control<OpportunityFormValues>
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  /** BR-1/D-2: the picked lead already has an opportunity, so the submit is refused before it is attempted. */
  isSubmitDisabled: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the opportunity create form, the detail's own
 * (`OpportunityDetailHeader` + `OpportunityStatsStrip`, spec 0198): monogram
 * and title follow what is being typed, the strip follows the planning
 * estimates, and the actions sit where the detail keeps its status badge — a
 * status that only exists once quotes do (spec 0082). A refused submit is
 * reported right under the actions.
 */
export function OpportunityFormHeader({
  control,
  formId,
  isSubmitting,
  isSubmitDisabled,
  submitError,
  onCancel,
}: OpportunityFormHeaderProps) {
  const { t } = useTranslation()
  const [name, estimatedValue, successProbability, startDate, expectedCloseDate] = useWatch({
    control,
    name: ['name', 'estimated_value', 'success_probability', 'start_date', 'expected_close_date'],
  })
  const heading = name.trim() || t('opportunities.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Handshake />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={t('opportunities.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('opportunities.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting || isSubmitDisabled}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('opportunities.form.saving') : t('opportunities.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <OpportunityStatsStrip
        estimatedValue={estimatedValue}
        successProbability={successProbability}
        startDate={startDate}
        expectedCloseDate={expectedCloseDate}
      />
    </>
  )
}
