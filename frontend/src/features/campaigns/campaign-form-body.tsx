import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { CampaignCreateSections } from '@/features/campaigns/campaign-create-sections'
import { CampaignFormHeader } from '@/features/campaigns/campaign-form-header'
import { useCampaignDraftValues } from '@/features/campaigns/use-campaign-draft-values'
import { useCampaignForm } from '@/features/campaigns/use-campaign-form'
import { useCampaignFormSubmit } from '@/features/campaigns/use-campaign-form-submit'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const CAMPAIGN_FORM_ID = 'campaign-form'

interface CampaignFormBodyProps {
  /** Duplicate (row action "duplicate"): the campaign the new one is prefilled from. */
  source?: CampaignDetail
  onSuccess: (campaign: CampaignDetail) => void
  onCancel: () => void
  /** The sequential code suggestion prefilled into the `code` row (spec 0025). */
  initialCode: string
}

/**
 * The campaign create form UI — a bare create or a duplicate, both through the
 * create path — as a replica of the campaign detail (spec 0221, spec 0195 D-8
 * applied to Campagne): the same `RecordCanvas`, the record card with its
 * identity band, KPI strip and sections, every row closed until clicked
 * (`CampaignCreateSections`). There is no edit form: the detail edits a
 * persisted campaign in place.
 */
export function CampaignFormBody({ source, onSuccess, onCancel, initialCode }: CampaignFormBodyProps) {
  const { t } = useTranslation()
  // Stable per source: a fresh object per render would rebuild the form's defaults.
  const mode = useMemo<CampaignFormMode>(
    () => (source ? { type: 'duplicate', source } : { type: 'create' }),
    [source],
  )
  const { form, customFieldErrorPaths } = useCampaignForm({ mode, initialCode })
  const { serverError, onSubmit } = useCampaignFormSubmit({
    form,
    mode,
    customFieldErrorPaths,
    suggestedCode: initialCode,
    onSuccess,
  })
  const draft = useDraftInlineEdit(form)
  const values = useCampaignDraftValues(form.control)
  const { isSubmitting } = form.formState

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={CAMPAIGN_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <CampaignFormHeader
                values={values}
                formId={CAMPAIGN_FORM_ID}
                isSubmitting={isSubmitting}
                submitError={serverError}
                onCancel={onCancel}
              />
              <CampaignCreateSections values={values} form={form} draft={draft} />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={CAMPAIGN_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('campaigns.form.save')}
              submittingLabel={t('campaigns.form.saving')}
              cancel={{ label: t('campaigns.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
