import type { FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCard } from '@/components/detail/record-panel'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { ExistingOpportunityAlert } from '@/features/opportunities/existing-opportunity-alert'
import { OpportunityCreateSections } from '@/features/opportunities/opportunity-create-sections'
import { QuoteCreateLines } from '@/features/quotes/quote-create-lines'
import { QuoteCreateAttributes, QuoteCreateSections } from '@/features/quotes/quote-create-sections'
import { QuoteFormHeader } from '@/features/quotes/quote-form-header'
import { OPPORTUNITY_STEP_FORM_ID, QUOTE_STEP_FORM_ID } from '@/features/quotes/with-opportunity/with-opportunity-nav'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'
import type { useQuoteWithOpportunity } from '@/features/quotes/with-opportunity/use-quote-with-opportunity'

type Wizard = ReturnType<typeof useQuoteWithOpportunity>

/** No lead in this procedure (spec 0396 D-4): the sections read an always-empty selection. */
const NO_LEAD_SELECTION: OpportunityLeadSelectionState = {
  leadId: null,
  lockedFields: [],
  existingOpportunityId: null,
  registry: null,
  isApplying: false,
  isError: false,
}

function ignoreLeadSelection(): void {}

interface OpportunityStepProps {
  wizard: Wizard
  permissions: ResourcePermissions | null
}

/**
 * Step 1: the opportunity form's own sections, minus the Lead. "Avanti" (the
 * form's submit, so Enter too) runs the step gate instead of a save.
 */
export function OpportunityStep({ wizard, permissions }: OpportunityStepProps) {
  const { t } = useTranslation()
  const { opportunityForm: form, blocking, goNext } = wizard
  const draft = useDraftInlineEdit(form)

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    void goNext()
  }

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <Form {...form}>
        <form id={OPPORTUNITY_STEP_FORM_ID} onSubmit={handleSubmit} className="contents" noValidate>
          <RecordCard>
            {blocking !== null ? (
              <div className="p-4 pb-0">
                <ExistingOpportunityAlert
                  opportunityId={blocking.id}
                  message={blocking.message}
                  offerLinkLabel={t('quotes.withOpportunity.offerOnExisting')}
                />
              </div>
            ) : null}
            <OpportunityCreateSections
              form={form}
              draft={draft}
              leadSelection={NO_LEAD_SELECTION}
              onSelectLead={ignoreLeadSelection}
              blockingOpportunity={null}
              hideLead
            />
          </RecordCard>
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}

interface QuoteStepProps {
  wizard: Wizard
  permissions: ResourcePermissions | null
}

/** Step 2: the offer form's own sections; the Opportunita' row reads "new", and Salva creates both. */
export function QuoteStep({ wizard, permissions }: QuoteStepProps) {
  const { t } = useTranslation()
  const { quoteForm, quoteDefaults, saveBoth } = wizard
  const { form } = quoteForm
  const draft = useDraftInlineEdit(form)

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <Form {...form}>
        <form id={QUOTE_STEP_FORM_ID} onSubmit={form.handleSubmit(saveBoth)} className="contents" noValidate>
          <RecordCard>
            <QuoteFormHeader quoteForm={quoteForm} />
            <QuoteCreateSections
              quoteForm={quoteForm}
              draft={draft}
              defaults={quoteDefaults}
              pendingOpportunityLabel={t('quotes.withOpportunity.newOpportunity')}
            />
            <QuoteCreateLines quoteForm={quoteForm} />
            <QuoteCreateAttributes quoteForm={quoteForm} draft={draft} />
          </RecordCard>
        </form>
      </Form>
    </ResourcePermissionsProvider>
  )
}
