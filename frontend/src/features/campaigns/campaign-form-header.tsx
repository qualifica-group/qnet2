import { useTranslation } from 'react-i18next'
import { Loader2, Megaphone, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { CampaignDetailStats } from '@/features/campaigns/campaign-detail-header'
import type { CampaignRecordValues } from '@/features/campaigns/campaign-record'

interface CampaignFormHeaderProps {
  /** The draft as the rows read it: name and code identify the campaign being created. */
  values: CampaignRecordValues
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the campaign create form, the detail's own
 * (`CampaignDetailHeader` + `CampaignDetailStats`, spec 0221): monogram and
 * title follow the name being typed, the subtitle the code, the strip the
 * budget, target and dates. A refused submit is reported right under the
 * actions.
 */
export function CampaignFormHeader({ values, formId, isSubmitting, submitError, onCancel }: CampaignFormHeaderProps) {
  const { t } = useTranslation()
  const heading = values.name.trim() || t('campaigns.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Megaphone />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={values.code || t('campaigns.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('campaigns.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('campaigns.form.saving') : t('campaigns.form.save')}
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

      <CampaignDetailStats campaign={values} />
    </>
  )
}
