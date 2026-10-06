import { useTranslation } from 'react-i18next'
import { ClipboardList, Lock } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { formatDate } from '@/lib/formatting/date-display'
import {
  WorkOrderCallbackDateField,
  WorkOrderCodeField,
  WorkOrderStartDateField,
  WorkOrderTaskTemplateField,
  WorkOrderTextAreaField,
  WorkOrderTitleField,
  WorkOrderTypeField,
} from '@/features/work-orders/work-order-identity-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderType } from '@/features/work-orders/types'

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

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? <>{value}</> : <DetailEmpty />
}

function DateValue({ value }: { value: string | null }) {
  return <>{formatDate(value) || <DetailEmpty />}</>
}

interface WorkOrderIdentitySectionProps {
  values: WorkOrderIdentityValues
  form: WorkOrderFormState
  inline: InlineEdit
}

/**
 * "Dati" of the work order record: every row edits in place on its own
 * control (spec 0195 applied to Commesse). `code` and the Modello di Task are
 * written at creation only: their field permission turns them read-only on
 * the detail (D-1/D-5, spec 0124 D-5), so the row shows no pencil there.
 */
export function WorkOrderIdentitySection({ values, form, inline }: WorkOrderIdentitySectionProps) {
  const { t } = useTranslation()
  const { control } = form.form

  return (
    <RecordSection title={t('workOrders.detail.sections.identity')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordInlineField
          field="title"
          label={t('workOrders.form.title')}
          inline={inline}
          editor={<WorkOrderTitleField control={control} />}
        >
          <TextValue value={values.title} />
        </RecordInlineField>
        <RecordInlineField
          field="code"
          label={t('workOrders.form.code')}
          inline={inline}
          editor={<WorkOrderCodeField control={control} />}
        >
          <TextValue value={values.code} />
        </RecordInlineField>
        <RecordInlineField
          field="type"
          label={t('workOrders.form.type')}
          inline={inline}
          editor={<WorkOrderTypeField control={control} />}
        >
          {t(`workOrders.options.type.${values.type}`)}
        </RecordInlineField>
        <RecordInlineField
          field="start_date"
          label={t('workOrders.form.startDate')}
          inline={inline}
          editor={<WorkOrderStartDateField control={control} />}
        >
          <DateValue value={values.start_date} />
        </RecordInlineField>
        <RecordInlineField
          field="callback_date"
          label={t('workOrders.form.callbackDate')}
          inline={inline}
          editor={<WorkOrderCallbackDateField control={control} />}
        >
          <DateValue value={values.callback_date} />
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('workOrders.detail.description')}
          inline={inline}
          editor={
            <WorkOrderTextAreaField control={control} name="description" label={t('workOrders.detail.description')} />
          }
        >
          {values.description ? (
            // Capped and scrollable: a long description must not push the rest of the section away.
            <p className="max-h-40 overflow-y-auto break-words whitespace-pre-wrap">{values.description}</p>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="task_template_id"
          label={t('workOrders.detail.taskTemplate')}
          inline={inline}
          editor={<WorkOrderTaskTemplateField control={control} selected={values.task_template} />}
        >
          {values.task_template ? (
            <RecordLink domain="task-templates" id={values.task_template.id}>
              {values.task_template.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        {values.force_close_reason !== null ? (
          <RecordField label={t('workOrders.detail.forceCloseReason')} icon={<Lock />}>
            <span className="whitespace-pre-wrap">{values.force_close_reason}</span>
          </RecordField>
        ) : null}
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

/**
 * "Note interne" across the record's full width, in the amber callout every
 * record shows its notes in (`GeneralNotesCallout`); a click on it (or its
 * pencil) opens the textarea. With no note, an editable record shows a plain
 * empty row to add one, a read-only one shows nothing at all.
 */
export function WorkOrderInternalNotesRow({ notes, form, inline, className }: WorkOrderInternalNotesRowProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const label = t('workOrders.detail.internalNotes')
  const hasNotes = notes !== null && notes.trim() !== ''
  const isEditing = inline.editingField === 'internal_notes'

  if (!hasNotes && !isEditing && !fieldPermission('internal_notes').editable) {
    return null
  }

  return (
    // A list of its own: the row form needs a `<dl>` around it, as in every section.
    <RecordFieldList className={className}>
      <RecordInlineField
        field="internal_notes"
        label={label}
        inline={inline}
        // Open, always a labelled row: a draft emptied while typing must not
        // swap the frame (and remount the textarea) under the cursor.
        layout={hasNotes && !isEditing ? 'block' : 'row'}
        editor={<WorkOrderTextAreaField control={form.form.control} name="internal_notes" label={label} />}
      >
        {hasNotes ? <GeneralNotesCallout title={label} notes={notes} /> : <DetailEmpty />}
      </RecordInlineField>
    </RecordFieldList>
  )
}
