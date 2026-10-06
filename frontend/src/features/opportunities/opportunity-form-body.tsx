import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { OpportunityCreateSections } from '@/features/opportunities/opportunity-create-sections'
import { OpportunityFormHeader } from '@/features/opportunities/opportunity-form-header'
import {
  useOpportunityForm,
  useOpportunityFormSubmit,
  type LeadSubmissionState,
} from '@/features/opportunities/use-opportunity-form'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import type { OpportunityCreateFormMode, OpportunityDetail } from '@/features/opportunities/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const OPPORTUNITY_FORM_ID = 'opportunity-form'

interface OpportunityFormBodyProps {
  mode: OpportunityCreateFormMode
  onSuccess: (opportunity: OpportunityDetail) => void
  onCancel: () => void
}

/**
 * The opportunity create form UI, a replica of the opportunity detail (spec
 * 0198, spec 0195 D-8 applied to Opportunita'): the same `RecordCanvas`, the
 * record card with its identity band, KPI strip and sections, every row
 * closed until clicked (`OpportunityCreateSections`). There is no edit form:
 * the detail edits a persisted opportunity in place.
 *
 * BR-2 field locking applies uniformly whether the lead came from the
 * `?lead_id=N` deep-link or from picking one in the form
 * (`useOpportunityLeadSelection`). Pure composition: every non-render concern
 * lives in `useOpportunityForm`/`useOpportunityFormSubmit`.
 */
export function OpportunityFormBody({ mode, onSuccess, onCancel }: OpportunityFormBodyProps) {
  const { t } = useTranslation()
  const { form } = useOpportunityForm({ mode })

  // `useOpportunityLeadSelection` needs `form.setValue`, so it can only run
  // AFTER `useOpportunityForm` — and `leadSubmission` (below) can only be
  // computed after `leadSelection` exists. This ordering, not a ref, is what
  // keeps `useOpportunityFormSubmit`'s `onSubmit` un-stale.
  const initialLead = mode.fromLead
  const leadSelection = useOpportunityLeadSelection(
    initialLead
      ? { leadId: initialLead.leadId, lockedFields: initialLead.lockedFields, registry: initialLead.references.registry }
      : null,
    form.setValue,
    form.getValues,
  )

  const leadIsBlocked = leadSelection.state.existingOpportunityId !== null
  const leadSubmission: LeadSubmissionState = {
    blocked: leadIsBlocked,
    fromLead:
      leadSelection.state.leadId !== null && !leadIsBlocked
        ? { leadId: leadSelection.state.leadId, lockedFields: leadSelection.state.lockedFields }
        : null,
  }

  const { serverError, blockingOpportunity, onSubmit } = useOpportunityFormSubmit({
    form,
    mode,
    leadSubmission,
    onSuccess,
  })
  const draft = useDraftInlineEdit(form)
  const { isSubmitting } = form.formState

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={OPPORTUNITY_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordBody side={null}>
            <RecordCard>
              <OpportunityFormHeader
                control={form.control}
                formId={OPPORTUNITY_FORM_ID}
                isSubmitting={isSubmitting}
                isSubmitDisabled={leadIsBlocked}
                submitError={serverError}
                onCancel={onCancel}
              />
              <OpportunityCreateSections
                form={form}
                draft={draft}
                leadSelection={leadSelection.state}
                onSelectLead={(leadId) => void leadSelection.selectLead(leadId)}
                blockingOpportunity={blockingOpportunity}
              />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={OPPORTUNITY_FORM_ID}
              isSubmitting={isSubmitting}
              isSubmitDisabled={leadIsBlocked}
              submitLabel={t('opportunities.form.save')}
              submittingLabel={t('opportunities.form.saving')}
              cancel={{ label: t('opportunities.form.cancel'), onCancel }}
            />
          </RecordBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
