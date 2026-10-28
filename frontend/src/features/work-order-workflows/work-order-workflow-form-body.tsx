import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Workflow } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import {
  WorkOrderWorkflowCriteriaField,
  WorkOrderWorkflowStatusesField,
} from '@/features/work-order-workflows/work-order-workflow-editors'
import { WorkOrderWorkflowGeneralRows } from '@/features/work-order-workflows/work-order-workflow-rows'
import { useWorkOrderWorkflowForm } from '@/features/work-order-workflows/use-work-order-workflow-form'
import type { WorkOrderWorkflowDetail } from '@/features/work-order-workflows/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const WORK_ORDER_WORKFLOW_FORM_ID = 'work-order-workflow-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface WorkOrderWorkflowFormBodyProps {
  onSuccess: (workOrderWorkflow: WorkOrderWorkflowDetail) => void
  onCancel: () => void
}

/**
 * The work order workflow create form as a replica of the detail (as the quote configurator):
 * name and active flag as rows closed until clicked (Fatto/Ripristina,
 * nothing saved per row), the criteria and statuses editors OPEN since they
 * are editors rather than fields; Salva
 * validates and creates everything at once.
 */
export function WorkOrderWorkflowFormBody({ onSuccess, onCancel }: WorkOrderWorkflowFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useWorkOrderWorkflowForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('workOrderWorkflows.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={WORK_ORDER_WORKFLOW_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('workOrderWorkflows.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Workflow />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('workOrderWorkflows.form.save'),
          saving: t('workOrderWorkflows.form.saving'),
          cancel: t('workOrderWorkflows.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('workOrderWorkflows.form.sections.identity.title')} full>
            <RecordFieldList>
              <WorkOrderWorkflowGeneralRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('workOrderWorkflows.form.sections.criteria.title')} full>
            <p className="mb-2 text-xs text-muted-foreground">{t('workOrderWorkflows.form.sections.criteria.description')}</p>
            <WorkOrderWorkflowCriteriaField />
          </RecordSection>
          <RecordSection title={t('workOrderWorkflows.form.sections.statuses.title')} full>
            <p className="mb-2 text-xs text-muted-foreground">{t('workOrderWorkflows.form.sections.statuses.description')}</p>
            <WorkOrderWorkflowStatusesField />
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
