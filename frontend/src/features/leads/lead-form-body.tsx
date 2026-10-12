import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { LeadCreateSections } from '@/features/leads/lead-create-sections'
import { LeadFormHeader } from '@/features/leads/lead-form-header'
import { useLeadCascades } from '@/features/leads/use-lead-cascades'
import { useLeadDraftValues } from '@/features/leads/use-lead-draft-values'
import { useLeadForm } from '@/features/leads/use-lead-form'
import { useLeadFormSubmit } from '@/features/leads/use-lead-form-submit'
import type { LeadDetail } from '@/features/leads/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const LEAD_FORM_ID = 'lead-form'

/** Stable create mode: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface LeadFormBodyProps {
  onSuccess: (lead: LeadDetail) => void
  onCancel: () => void
}

/**
 * The lead create form UI, a replica of the lead detail (spec 0217, spec 0195
 * D-8 applied to Lead): the same `RecordCanvas`, the record card with its
 * identity band, KPI strip and sections, every row closed until clicked
 * (`LeadCreateSections`), the cascades firing inside the open row as on the
 * detail. There is no edit form: the detail edits a persisted lead in place.
 */
export function LeadFormBody({ onSuccess, onCancel }: LeadFormBodyProps) {
  const { t } = useTranslation()
  const form = useLeadForm({ mode: CREATE_MODE })
  const cascades = useLeadCascades(form, null)
  const { serverError, onSubmit } = useLeadFormSubmit({ form, mode: CREATE_MODE, onSuccess })
  const draftRows = useDraftInlineEdit(form)
  const values = useLeadDraftValues(form.control)
  const { isSubmitting } = form.formState
  const isPending = cascades.isCampaignChangePending

  // The cascades re-anchor on the value the row opens with; a campaign switch
  // awaiting its products confirmation keeps the row's buttons disabled (AC-042).
  const draft: InlineEdit = {
    ...draftRows,
    start: (field) => {
      cascades.syncBaseline()
      draftRows.start(field)
    },
    isSaving: isPending,
  }

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={LEAD_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <LeadFormHeader
                values={values}
                formId={LEAD_FORM_ID}
                isSubmitting={isSubmitting}
                isSubmitDisabled={isPending}
                submitError={serverError}
                onCancel={onCancel}
              />
              <LeadCreateSections values={values} form={form} draft={draft} cascades={cascades} />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={LEAD_FORM_ID}
              isSubmitting={isSubmitting}
              isSubmitDisabled={isPending}
              submitLabel={t('leads.form.save')}
              submittingLabel={t('leads.form.saving')}
              cancel={{ label: t('leads.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
