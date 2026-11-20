import { useTranslation } from 'react-i18next'
import { Activity, ClipboardList } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { GENERAL_NOTES_TEXTAREA_CLASS } from '@/components/record-form/layout'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { WorkOrderTaskTemplateField, WorkOrderTextAreaField } from '@/features/work-orders/work-order-identity-fields'
import {
  WorkOrderCallbackDateRow,
  WorkOrderCodeRow,
  WorkOrderDescriptionRow,
  WorkOrderForceCloseReasonRow,
  WorkOrderStartDateRow,
  WorkOrderTitleRow,
  WorkOrderTypeRow,
} from '@/features/work-orders/work-order-record-rows'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import {
  WorkOrderWorkflowStatusField,
  WorkOrderWorkflowStatusValue,
} from '@/features/work-orders/work-order-workflow-status-field'
import type { WorkOrderType, WorkOrderWorkflowStatusRef } from '@/features/work-orders/types'

/** What the identity rows show: the persisted record on the detail, the draft on create. */
export interface WorkOrderIdentityValues {
  title: string
  code: string
  type: WorkOrderType
  start_date: string | null
  callback_date: string | null
  description: string | null
  task_template: RelationFieldRef | null
  /** Set only while the closure is forced: written by the header's action, read-only here. */
  force_close_reason: string | null
}

/** The "Stato lavorazione" of a persisted work order and its resolved set: absent on create (the server assigns `open`). */
export interface WorkOrderWorkflowStatusContext {
  current: WorkOrderWorkflowStatusRef
  statuses: WorkOrderWorkflowStatusRef[]
}

interface WorkOrderIdentitySectionProps {
  values: WorkOrderIdentityValues
  form: WorkOrderFormState
  inline: InlineEdit
}

/**
 * "Dati" of the create form: every row edits in place on its own control.
 * `code` and the Modello di Task are written at creation only. The detail
 * spreads the same rows over "Dati contrattuali commessa" and "Descrizione
 * commessa" (user directives 2026-11-16).
 */
export function WorkOrderIdentitySection({ values, form, inline }: WorkOrderIdentitySectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('workOrders.detail.sections.identity')} icon={<ClipboardList />}>
      <RecordFieldList>
        <WorkOrderTitleRow form={form} inline={inline} title={values.title} />
        <WorkOrderCodeRow form={form} inline={inline} code={values.code} />
        <WorkOrderTypeRow form={form} inline={inline} type={values.type} />
        <WorkOrderStartDateRow form={form} inline={inline} startDate={values.start_date} />
        <WorkOrderCallbackDateRow form={form} inline={inline} callbackDate={values.callback_date} />
        <WorkOrderDescriptionRow form={form} inline={inline} description={values.description} />
        <RecordInlineField
          field="task_template_id"
          label={t('workOrders.detail.taskTemplate')}
          inline={inline}
          editor={<WorkOrderTaskTemplateField control={form.form.control} selected={values.task_template} />}
        >
          {values.task_template ? (
            <RecordLink domain="task-templates" id={values.task_template.id}>
              {values.task_template.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <WorkOrderForceCloseReasonRow reason={values.force_close_reason} />
      </RecordFieldList>
    </RecordSection>
  )
}

interface WorkOrderWorkflowStatusSectionProps {
  workflowStatus: WorkOrderWorkflowStatusContext
  form: WorkOrderFormState
  inline: InlineEdit
}

/**
 * "Stato lavorazione" of a persisted work order (spec 0322) as its own
 * section, under the contract data in the detail's left column (user
 * directive 2026-11-16), still edited in place.
 */
export function WorkOrderWorkflowStatusSection({ workflowStatus, form, inline }: WorkOrderWorkflowStatusSectionProps) {
  const { t } = useTranslation()
  const label = t('workOrders.detail.workflowStatus')

  return (
    <RecordSection title={label} icon={<Activity />}>
      <RecordFieldList>
        <RecordInlineField
          field="work_order_workflow_status_id"
          label={label}
          inline={inline}
          editor={<WorkOrderWorkflowStatusField control={form.form.control} statuses={workflowStatus.statuses} />}
        >
          <WorkOrderWorkflowStatusValue status={workflowStatus.current} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface WorkOrderInternalNotesRowProps {
  notes: string | null
  form: WorkOrderFormState
  inline: InlineEdit
  className?: string
}

/** "Note commessa" across the record's full width, in the request work panel's callout (`NotesCalloutRow`). */
export function WorkOrderInternalNotesRow({ notes, form, inline, className }: WorkOrderInternalNotesRowProps) {
  const { t } = useTranslation()
  const label = t('workOrders.detail.internalNotes')

  return (
    <NotesCalloutRow
      field="internal_notes"
      title={label}
      notes={notes}
      placeholder={t('workOrders.detail.internalNotesPlaceholder')}
      inline={inline}
      editor={
        <WorkOrderTextAreaField
          control={form.form.control}
          name="internal_notes"
          label={label}
          className={GENERAL_NOTES_TEXTAREA_CLASS}
        />
      }
      className={className}
    />
  )
}
