import { useTranslation } from 'react-i18next'
import { Contact, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { LeadStatsStrip } from '@/features/leads/lead-detail-header'
import { campaignLabel, type LeadRecordValues } from '@/features/leads/lead-record'

interface LeadFormHeaderProps {
  /** The draft as the rows read it: the anagrafica names the lead, the campaign subtitles it. */
  values: LeadRecordValues
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  /** A campaign switch waiting on the products confirmation (AC-042). */
  isSubmitDisabled: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the lead create form, the detail's own
 * (`LeadDetailHeader` + `LeadStatsStrip`, spec 0217): monogram and title
 * follow the chosen anagrafica, the subtitle the campaign, the strip the
 * draft; the actions sit where the detail keeps its conversion action. A
 * refused submit is reported right under the actions.
 */
export function LeadFormHeader({
  values,
  formId,
  isSubmitting,
  isSubmitDisabled,
  submitError,
  onCancel,
}: LeadFormHeaderProps) {
  const { t } = useTranslation()
  const heading = values.registry?.name ?? t('leads.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Contact />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={values.campaign ? campaignLabel(values.campaign) : t('leads.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('leads.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting || isSubmitDisabled}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('leads.form.saving') : t('leads.form.save')}
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

      <LeadStatsStrip
        operator={values.operator?.name ?? null}
        site={values.operational_site?.label ?? null}
        source={values.source?.name ?? null}
        productCount={values.products_of_interest.length}
      />
    </>
  )
}
