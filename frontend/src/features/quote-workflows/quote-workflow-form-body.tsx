import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Workflow } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import {
  QuoteWorkflowCriteriaField,
  QuoteWorkflowStatusesField,
} from '@/features/quote-workflows/quote-workflow-editors'
import { QuoteWorkflowGeneralRows } from '@/features/quote-workflows/quote-workflow-rows'
import { useQuoteWorkflowForm } from '@/features/quote-workflows/use-quote-workflow-form'
import type { QuoteWorkflowDetail } from '@/features/quote-workflows/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const QUOTE_WORKFLOW_FORM_ID = 'quote-workflow-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface QuoteWorkflowFormBodyProps {
  onSuccess: (quoteWorkflow: QuoteWorkflowDetail) => void
  onCancel: () => void
}

/**
 * The opportunity workflow create form as a replica of the detail (spec 0242):
 * name and active flag as rows closed until clicked (Fatto/Ripristina,
 * nothing saved per row), the criteria and statuses editors OPEN since they
 * are editors rather than fields (precedent: spec 0197, 0227); Salva
 * validates and creates everything at once.
 */
export function QuoteWorkflowFormBody({ onSuccess, onCancel }: QuoteWorkflowFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useQuoteWorkflowForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('quoteWorkflows.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={QUOTE_WORKFLOW_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('quoteWorkflows.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Workflow />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('quoteWorkflows.form.save'),
          saving: t('quoteWorkflows.form.saving'),
          cancel: t('quoteWorkflows.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('quoteWorkflows.form.sections.identity.title')} full>
            <RecordFieldList>
              <QuoteWorkflowGeneralRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('quoteWorkflows.form.sections.criteria.title')} full>
            <p className="mb-2 text-xs text-muted-foreground">{t('quoteWorkflows.form.sections.criteria.description')}</p>
            <QuoteWorkflowCriteriaField />
          </RecordSection>
          <RecordSection title={t('quoteWorkflows.form.sections.statuses.title')} full>
            <p className="mb-2 text-xs text-muted-foreground">{t('quoteWorkflows.form.sections.statuses.description')}</p>
            <QuoteWorkflowStatusesField />
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
