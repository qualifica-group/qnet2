import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Contact, Link2, MessageSquareWarning, Repeat } from 'lucide-react'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { LEADS_FOR_SELECT_RESOURCE } from '@/features/leads/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE } from '@/features/opportunities/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { TaskClosureFlagField, TaskIsCompletedField } from '@/features/tasks/task-closure-section'
import {
  ForSelectLabelDisplay,
  RecurrenceDisplay,
  WorkOrderStageDisplay,
  YesNoDisplay,
} from '@/features/tasks/task-create-displays'
import { leadRefOf, workOrderRefOf, workOrderStageOf } from '@/features/tasks/task-form-hydration'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import {
  TaskLeadField,
  TaskOpportunityField,
  TaskWorkOrderField,
  TaskWorkOrderStageField,
} from '@/features/tasks/task-links-section'
import { TaskRecurrenceFields } from '@/features/tasks/task-recurrence-section'
import { TaskReferentField, TaskRegistryField } from '@/features/tasks/task-registry-section'
import type { TaskDetail } from '@/features/tasks/types'
import type { TaskFormState } from '@/features/tasks/use-task-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

interface TaskCreateLinkSectionsProps {
  taskForm: TaskFormState
  draft: InlineEdit
  source: TaskDetail | null
}

/**
 * The create form's replica of the detail, part two (spec 0195 D-8): linked
 * records, closure flags and the recurrence rule, rows closed until clicked
 * (see `TaskCreateSections`). The anagrafica/commessa/opportunita' editors
 * keep the form's cascade (spec 0154 D-11). A plain create falls back to the
 * "crea sotto-task" parent prefill and the Commessa's own prefill for labels.
 */
export function TaskCreateLinkSections({ taskForm, draft, source }: TaskCreateLinkSectionsProps) {
  const { t } = useTranslation()
  const {
    form,
    parentPrefillRefs,
    workOrderPrefillRef,
    handleRegistryChange,
    handleOpportunityChange,
    handleWorkOrderChange,
    handleWorkOrderItemChange,
  } = taskForm
  const { control } = form
  const values = useWatch({ control })
  const registry = source?.registry ?? parentPrefillRefs.registry
  const referent = source?.referent ?? parentPrefillRefs.referent
  const opportunity = source?.opportunity ?? parentPrefillRefs.opportunity
  const workOrder = workOrderRefOf(source) ?? workOrderPrefillRef ?? parentPrefillRefs.workOrder
  const workOrderId = values.work_order_id ?? null
  // Spec 0146 D-3: the fase exists only on a commessa-linked task with no parent.
  const showStage = workOrderId !== null && (values.parent_task_id ?? null) === null

  return (
    <>
      <RecordSection title={t('tasks.detail.sections.links')} icon={<Link2 />}>
        <RecordFieldList>
          <RecordInlineField
            field="registry_id"
            label={t('tasks.detail.registry')}
            icon={<Contact />}
            inline={draft}
            editor={<TaskRegistryField control={control} registry={registry} onRegistryChange={handleRegistryChange} />}
          >
            <ForSelectLabelDisplay resource={REGISTRIES_FOR_SELECT_RESOURCE} id={values.registry_id ?? null} known={registry} />
          </RecordInlineField>
          <RecordInlineField
            field="referent_id"
            label={t('tasks.detail.referent')}
            inline={draft}
            // AC-080: the referent picker is scoped to (and needs) an anagrafica.
            canEdit={(values.registry_id ?? null) !== null}
            editor={<TaskReferentField control={control} referent={referent} />}
          >
            <ForSelectLabelDisplay resource={REFERENTS_FOR_SELECT_RESOURCE} id={values.referent_id ?? null} known={referent} />
          </RecordInlineField>
          <RecordInlineField
            field="opportunity_id"
            label={t('tasks.detail.opportunity')}
            inline={draft}
            editor={
              <TaskOpportunityField control={control} opportunity={opportunity} onOpportunityChange={handleOpportunityChange} />
            }
          >
            <ForSelectLabelDisplay
              resource={OPPORTUNITIES_FOR_SELECT_RESOURCE}
              id={values.opportunity_id ?? null}
              known={opportunity}
            />
          </RecordInlineField>
          <RecordInlineField
            field="work_order_id"
            label={t('tasks.detail.workOrder')}
            inline={draft}
            editor={
              <>
                <TaskWorkOrderField
                  control={control}
                  workOrder={workOrder}
                  onWorkOrderChange={handleWorkOrderChange}
                  onWorkOrderItemChange={handleWorkOrderItemChange}
                />
                {/* Picking a commessa clears the fase (D-3): offer the new one right here. */}
                <TaskWorkOrderStageField control={control} workOrderStage={workOrderStageOf(source)} />
              </>
            }
          >
            <ForSelectLabelDisplay resource={WORK_ORDERS_FOR_SELECT_RESOURCE} id={workOrderId} known={workOrder} />
          </RecordInlineField>
          {showStage ? (
            <RecordInlineField
              field="work_order_stage_id"
              label={t('tasks.form.workOrderStage')}
              inline={draft}
              editor={<TaskWorkOrderStageField control={control} workOrderStage={workOrderStageOf(source)} />}
            >
              <WorkOrderStageDisplay workOrderId={workOrderId} stageId={values.work_order_stage_id ?? null} />
            </RecordInlineField>
          ) : null}
          <RecordInlineField field="lead_id" label={t('tasks.form.lead')} inline={draft} editor={<TaskLeadField control={control} lead={leadRefOf(source)} />}>
            <ForSelectLabelDisplay resource={LEADS_FOR_SELECT_RESOURCE} id={values.lead_id ?? null} known={leadRefOf(source)} />
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('tasks.detail.sections.closure')} icon={<MessageSquareWarning />}>
        <RecordFieldList>
          <RecordInlineField
            field="requires_closure_feedback"
            label={t('tasks.detail.requiresClosureFeedback')}
            inline={draft}
            editor={<TaskClosureFlagField control={control} name="requires_closure_feedback" />}
          >
            <YesNoDisplay value={values.requires_closure_feedback ?? false} />
          </RecordInlineField>
          <RecordInlineField
            field="requires_validation"
            label={t('tasks.detail.requiresValidation')}
            inline={draft}
            editor={<TaskClosureFlagField control={control} name="requires_validation" />}
          >
            <YesNoDisplay value={values.requires_validation ?? false} />
          </RecordInlineField>
          <RecordInlineField field="is_completed" label={t('tasks.form.isCompleted')} inline={draft} editor={<TaskIsCompletedField control={control} />}>
            <YesNoDisplay value={values.is_completed ?? false} />
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('tasks.form.sections.recurrence.title')} icon={<Repeat />}>
        <RecordFieldList>
          <RecordInlineField
            field="recurrence"
            label={t('tasks.detail.recurrenceRule')}
            inline={draft}
            editor={<TaskRecurrenceFields control={control} />}
          >
            <RecurrenceDisplay recurrence={form.getValues('recurrence')} />
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>
    </>
  )
}
