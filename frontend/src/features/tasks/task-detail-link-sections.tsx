import { useTranslation } from 'react-i18next'
import { Contact, Link2, MessageSquareWarning, Repeat } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordLink } from '@/components/detail/record-link'
import { TaskClosureFlagField } from '@/features/tasks/task-closure-section'
import { leadRefOf, workOrderRefOf, workOrderStageOf } from '@/features/tasks/task-form-hydration'
import { TaskInlineField } from '@/features/tasks/task-inline-field'
import {
  TaskLeadField,
  TaskOpportunityField,
  TaskWorkOrderField,
  TaskWorkOrderStageField,
} from '@/features/tasks/task-links-section'
import { TaskRecurrenceFields } from '@/features/tasks/task-recurrence-section'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { TaskReferentField, TaskRegistryField } from '@/features/tasks/task-registry-section'
import type { TaskDetailSectionProps } from '@/features/tasks/task-detail-sections'

/**
 * The editable sections of the task detail (spec 0195), part two: linked
 * records, closure flags and the recurrence rule. Same `TaskInlineField`
 * pattern as `task-detail-sections.tsx`.
 *
 * The anagrafica/commessa/opportunita' editors keep the form's cascade
 * (`useTaskForm` handlers, spec 0154 D-11): a value they clear travels in the
 * same PATCH as the one the user picked.
 */

export function TaskDetailLinksSection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const {
    form,
    inline,
    handleRegistryChange,
    handleOpportunityChange,
    handleWorkOrderChange,
    handleWorkOrderItemChange,
  } = editor
  // Spec 0146 D-3: the fase exists only on a commessa-linked task with no parent.
  const showStage = task.work_order_id !== null && task.parent_task_id === null

  return (
    <RecordSection title={t('tasks.detail.sections.links')} icon={<Link2 />}>
      <RecordFieldList>
        <TaskInlineField
          field="registry_id"
          label={t('tasks.detail.registry')}
          icon={<Contact />}
          inline={inline}
          editor={
            <TaskRegistryField control={form.control} registry={task.registry} onRegistryChange={handleRegistryChange} />
          }
        >
          {task.registry ? (
            <RecordLink domain="registries" id={task.registry.id}>
              {task.registry.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </TaskInlineField>
        <TaskInlineField
          field="referent_id"
          label={t('tasks.detail.referent')}
          inline={inline}
          // AC-080: the referent picker is scoped to (and needs) an anagrafica.
          canEdit={task.registry_id !== null}
          editor={<TaskReferentField control={form.control} referent={task.referent} />}
        >
          {task.referent ? (
            <RecordLink domain="referents" id={task.referent.id}>
              {task.referent.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </TaskInlineField>
        <TaskInlineField
          field="opportunity_id"
          label={t('tasks.detail.opportunity')}
          inline={inline}
          editor={
            <TaskOpportunityField
              control={form.control}
              opportunity={task.opportunity}
              onOpportunityChange={handleOpportunityChange}
            />
          }
        >
          {task.opportunity ? (
            <RecordLink domain="opportunities" id={task.opportunity.id}>
              {task.opportunity.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </TaskInlineField>
        <TaskInlineField
          field="work_order_id"
          label={t('tasks.detail.workOrder')}
          inline={inline}
          editor={
            <>
              <TaskWorkOrderField
                control={form.control}
                workOrder={workOrderRefOf(task)}
                onWorkOrderChange={handleWorkOrderChange}
                onWorkOrderItemChange={handleWorkOrderItemChange}
              />
              {/* Picking a commessa clears the fase (D-3): offer the new one right here. */}
              <TaskWorkOrderStageField control={form.control} workOrderStage={workOrderStageOf(task)} />
            </>
          }
        >
          {task.work_order ? (
            <RecordLink domain="work-orders" id={task.work_order.id}>
              {`${task.work_order.code} — ${task.work_order.title}`}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </TaskInlineField>
        {showStage ? (
          <TaskInlineField
            field="work_order_stage_id"
            label={t('tasks.form.workOrderStage')}
            inline={inline}
            editor={<TaskWorkOrderStageField control={form.control} workOrderStage={workOrderStageOf(task)} />}
          >
            {task.work_order_stage?.name ?? <DetailEmpty />}
          </TaskInlineField>
        ) : null}
        <TaskInlineField
          field="lead_id"
          label={t('tasks.form.lead')}
          inline={inline}
          editor={<TaskLeadField control={form.control} lead={leadRefOf(task)} />}
        >
          {task.lead ? (
            <RecordLink domain="leads" id={task.lead.id}>
              {task.lead.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </TaskInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

export function TaskDetailClosureSection({ task, editor }: TaskDetailSectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSection title={t('tasks.detail.sections.closure')} icon={<MessageSquareWarning />}>
      <RecordFieldList>
        <TaskInlineField
          field="requires_closure_feedback"
          label={t('tasks.detail.requiresClosureFeedback')}
          inline={inline}
          editor={<TaskClosureFlagField control={form.control} name="requires_closure_feedback" />}
        >
          {t(task.requires_closure_feedback ? 'common.yes' : 'common.no')}
        </TaskInlineField>
        <TaskInlineField
          field="requires_validation"
          label={t('tasks.detail.requiresValidation')}
          inline={inline}
          editor={<TaskClosureFlagField control={form.control} name="requires_validation" />}
        >
          {t(task.requires_validation ? 'common.yes' : 'common.no')}
        </TaskInlineField>
        {/* Written only from the completion pop-up (spec 0121 D-7). */}
        <RecordField label={t('tasks.detail.closureFeedback')}>
          {task.closure_feedback ? (
            <span className="whitespace-pre-wrap">{task.closure_feedback}</span>
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

export function TaskDetailRecurrenceSection({ task, editor }: TaskDetailSectionProps) {
  const { t, i18n } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSection title={t('tasks.form.sections.recurrence.title')} icon={<Repeat />}>
      <RecordFieldList>
        <TaskInlineField
          field="recurrence"
          label={t('tasks.detail.recurrenceRule')}
          inline={inline}
          editor={<TaskRecurrenceFields control={form.control} />}
        >
          {task.recurrence
            ? formatTaskRecurrenceRule(task.recurrence, t, i18n.language)
            : t('tasks.form.summary.recurrenceOff')}
        </TaskInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
