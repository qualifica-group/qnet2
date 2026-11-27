import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { GENERAL_NOTES_TEXTAREA_CLASS } from '@/components/record-form/layout'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import { cn } from '@/lib/utils'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { WorkOrderTaskTemplateField, WorkOrderTextAreaField } from '@/features/work-orders/work-order-identity-fields'
import {
  WorkOrderCallbackDateRow,
  WorkOrderCodeRow,
  WorkOrderForceCloseReasonRow,
  WorkOrderStartDateRow,
  WorkOrderTitleRow,
  WorkOrderTypeRow,
} from '@/features/work-orders/work-order-record-rows'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderType } from '@/features/work-orders/types'

/** What the identity rows show: the persisted record on the detail, the draft on create. */
export interface WorkOrderIdentityValues {
  title: string
  code: string
  type: WorkOrderType
  start_date: string | null
  callback_date: string | null
  task_template: RelationFieldRef | null
  /** Set only while the closure is forced: written by the header's action, read-only here. */
  force_close_reason: string | null
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

/** User directive 2026-11-27: a long description scrolls past 100px, reading and writing alike (300px narrowed to 100px the same day). */
const DESCRIPTION_MAX_HEIGHT_CLASS = 'max-h-[100px] overflow-y-auto'

interface WorkOrderDescriptionCalloutRowProps {
  description: string | null
  form: WorkOrderFormState
  inline: InlineEdit
  className?: string
}

/**
 * "Descrizione" in the request work panel's callout (`NotesCalloutRow`): the
 * yellow box that was "Note commessa" (user decision 2026-11-27, the notes
 * were merged into `description`). The detail puts it on top of the right
 * column, the create form across its full width.
 */
export function WorkOrderDescriptionCalloutRow({ description, form, inline, className }: WorkOrderDescriptionCalloutRowProps) {
  const { t } = useTranslation()
  const label = t('workOrders.detail.description')

  return (
    <NotesCalloutRow
      field="description"
      title={label}
      notes={description}
      placeholder={t('workOrders.detail.descriptionPlaceholder')}
      textClassName={DESCRIPTION_MAX_HEIGHT_CLASS}
      inline={inline}
      editor={
        <WorkOrderTextAreaField
          control={form.form.control}
          name="description"
          label={label}
          className={cn(GENERAL_NOTES_TEXTAREA_CLASS, DESCRIPTION_MAX_HEIGHT_CLASS)}
        />
      }
      className={className}
    />
  )
}
