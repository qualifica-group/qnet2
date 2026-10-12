/**
 * The time entry's rows (spec 0226 D-2), shared by the detail (saved one at a
 * time) and the create draft (kept until Aggiungi): Titolo, Data, Tipo, one
 * "Contesto segnatempo" row for the whole D-5 cascade, one "Orario" row for
 * Dalle/Alle/Tempo (the times drive the minutes), Note.
 */

import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Clock, Link2 } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { Badge } from '@/components/ui/badge'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { TimeEntryContextFields } from '@/features/time-entries/form/time-entry-context-fields'
import {
  TimeEntryDateField,
  TimeEntryNotesField,
  TimeEntryScheduleFields,
  TimeEntryTitleField,
  TimeEntryTypeField,
  type TimeEntryEditorFieldValues,
} from '@/features/time-entries/form/time-entry-fields'
import { TIME_ENTRY_ROW_FIELD } from '@/features/time-entries/form/time-entry-row-fields'
import type { TimeEntryRecordSectionProps, TimeEntryRecordValues } from '@/features/time-entries/form/time-entry-record'
import { TimeEntryTypeChip } from '@/features/time-entries/form/time-entry-type-chip'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? (
    <span className="break-words whitespace-pre-wrap">{value}</span>
  ) : (
    <DetailEmpty />
  )
}

function TypeValue({ type }: { type: TimeEntryRecordValues['task_type'] }) {
  if (!type) {
    return <DetailEmpty />
  }
  return (
    <Badge variant="secondary" className={cn('gap-1', badgeColorClass(type.color))}>
      <DynamicIcon name={type.icon} className="size-3" />
      {type.name}
    </Badge>
  )
}

/** `09:00 - 10:30 · 1h 30m`; the time alone when the entry carries no schedule (both times are optional). */
function ScheduleValue({ values }: { values: TimeEntryRecordValues }) {
  if (values.minutes === null) {
    return <DetailEmpty />
  }
  const duration = formatMinutesLabel(values.minutes)
  return (
    <span className="tabular-nums">
      {values.start_time && values.end_time ? `${values.start_time} - ${values.end_time} · ${duration}` : duration}
    </span>
  )
}

interface ContextLineProps {
  label: string
  domain?: string
  value: { id: number; name: string } | null
}

function ContextLine({ label, domain, value }: ContextLineProps) {
  if (!value) {
    return null
  }
  return (
    <li className="flex min-w-0 gap-1.5">
      <span className="shrink-0 text-muted-foreground">{label}:</span>
      {domain ? (
        <RecordLink domain={domain} id={value.id} className="min-w-0 truncate">
          {value.name}
        </RecordLink>
      ) : (
        <span className="min-w-0 truncate">{value.name}</span>
      )}
    </li>
  )
}

function ContextValue({ values }: { values: TimeEntryRecordValues }) {
  const { t } = useTranslation()
  const lines: ContextLineProps[] = [
    { label: t('timeEntries.form.registry'), domain: 'registries', value: values.registry },
    { label: t('timeEntries.form.opportunity'), domain: 'opportunities', value: values.opportunity },
    { label: t('timeEntries.form.workOrder'), domain: 'work-orders', value: values.work_order },
    { label: t('timeEntries.form.workOrderStage'), value: values.work_order_stage },
    { label: t('timeEntries.form.activity'), domain: 'tasks', value: values.task },
  ]
  if (lines.every((line) => line.value === null)) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex min-w-0 flex-col gap-0.5 text-sm">
      {lines.map((line) => (
        <ContextLine key={line.label} {...line} />
      ))}
    </ul>
  )
}

/**
 * A grouped row's editor: the row's label names the group ("Orario"), so every
 * field inside keeps its own visible label, the row's key field included.
 */
function GroupEditor({ children }: { children: ReactNode }) {
  return <MetaFieldRowContext.Provider value={null}>{children}</MetaFieldRowContext.Provider>
}

interface SectionShellProps {
  title: string
  icon: ReactNode
  children: ReactNode
}

function SectionShell({ title, icon, children }: SectionShellProps) {
  return (
    <RecordSection title={title} icon={icon}>
      <RecordFieldList>{children}</RecordFieldList>
    </RecordSection>
  )
}

/** "Dettagli": title, date, type, schedule and notes. */
function TimeEntryActivityRecordSection({ values, form, inline, controls, canEdit }: TimeEntryRecordSectionProps) {
  const { t } = useTranslation()
  // The rows' field controls are typed on the task-embedded shape (no fase), a subset of this form.
  const control = form.control as unknown as Control<TimeEntryEditorFieldValues>

  return (
    <SectionShell title={t('timeEntries.form.activitySectionTitle')} icon={<Clock />}>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.title}
        label={t('timeEntries.form.title')}
        inline={inline}
        // D-3: a linked task imposes its own title.
        canEdit={canEdit && !controls.isTaskLinked}
        editor={<TimeEntryTitleField control={control} linkedTaskTitle={null} />}
      >
        <TextValue value={values.title} />
      </RecordInlineField>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.date}
        label={t('timeEntries.form.date')}
        inline={inline}
        canEdit={canEdit}
        editor={<TimeEntryDateField control={control} />}
      >
        {values.date ? <span>{formatDate(values.date)}</span> : <DetailEmpty />}
      </RecordInlineField>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.type}
        label={t('timeEntries.form.type')}
        inline={inline}
        canEdit={canEdit}
        editor={<TimeEntryTypeField control={control} />}
      >
        <TypeValue type={values.task_type} />
      </RecordInlineField>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.schedule}
        label={t('timeEntries.form.scheduleRow')}
        inline={inline}
        canEdit={canEdit}
        editor={
          <GroupEditor>
            <div className="flex flex-col gap-2">
              <TimeEntryScheduleFields
                control={control}
                onStartTimeChange={controls.onStartTimeChange}
                onEndTimeChange={controls.onEndTimeChange}
              />
              <p className="text-xs text-muted-foreground">{t('timeEntries.form.minutesHint')}</p>
            </div>
          </GroupEditor>
        }
      >
        <ScheduleValue values={values} />
      </RecordInlineField>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.notes}
        label={t('timeEntries.form.notes')}
        inline={inline}
        canEdit={canEdit}
        editor={<TimeEntryNotesField control={control} />}
      >
        <TextValue value={values.notes} />
      </RecordInlineField>
    </SectionShell>
  )
}

/** "Contesto segnatempo": ONE row, the D-5 cascade (Cliente, Opportunita'/Commessa, Fase, Attivita') in one editor. */
function TimeEntryContextRecordSection({ values, form, inline, controls, canEdit }: TimeEntryRecordSectionProps) {
  const { t } = useTranslation()

  return (
    <SectionShell title={t('timeEntries.form.contextTitle')} icon={<Link2 />}>
      <RecordInlineField
        field={TIME_ENTRY_ROW_FIELD.context}
        label={t('timeEntries.form.contextRow')}
        inline={inline}
        canEdit={canEdit}
        editor={
          <GroupEditor>
            <TimeEntryContextFields
              control={form.control}
              registry={values.registry}
              opportunity={values.opportunity}
              workOrder={values.work_order}
              task={values.task}
              stage={controls.persistedStage}
              isTaskLinked={controls.isTaskLinked}
              taskDetail={controls.linkedTask}
              onRegistryChange={controls.onRegistryChange}
              onOpportunityItemChange={controls.onOpportunityItemChange}
              onWorkOrderItemChange={controls.onWorkOrderItemChange}
            />
          </GroupEditor>
        }
      >
        <ContextValue values={values} />
      </RecordInlineField>
    </SectionShell>
  )
}

interface TimeEntryRecordProps extends TimeEntryRecordSectionProps {
  title: string
  subtitle: string
  /** Below the record card, e.g. the create form's server error. */
  children?: ReactNode
}

/** The whole record, detail and create draft alike: identity band with the type chip, then the two sections. */
export function TimeEntryRecord({ title, subtitle, children, ...sectionProps }: TimeEntryRecordProps) {
  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<TimeEntryTypeChip type={sectionProps.values.task_type} />}
          title={title}
          subtitle={subtitle}
        />
        <RecordSectionsGrid>
          <TimeEntryActivityRecordSection {...sectionProps} />
          <TimeEntryContextRecordSection {...sectionProps} />
        </RecordSectionsGrid>
      </RecordCard>
      {children}
    </RecordCanvas>
  )
}
