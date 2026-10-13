/**
 * The time entry's own field controls (spec 0226 D-2), shared by the record
 * rows (detail and create draft) and by the task-embedded `TimeEntryEditor`:
 * one control per field, so a row's editor is the very control the embedded
 * card shows (spec 0195 D-3).
 */

import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { minutesToTimeValue, timeValueToMinutes } from '@/features/time-entries/time-entry-format'
import { TimeEntryTypePicker } from '@/features/time-entries/form/time-entry-type-picker'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'

/**
 * The fields every caller's form values share (spec 0122 D-9's task-scoped
 * shadow schema, `time-entries/task/task-time-entry-schema.ts`, mirrors these
 * exactly). `work_order_stage_id` (spec 0163) is deliberately excluded: only
 * the standalone record reads/writes it, through `TimeEntryContextFields`.
 */
export type TimeEntryEditorFieldValues = Omit<TimeEntryFormValues, 'work_order_stage_id'>

interface FieldProps {
  control: Control<TimeEntryEditorFieldValues>
  disabled?: boolean
}

interface TitleFieldProps extends FieldProps {
  /** With a task linked the server imposes its title (spec 0122 D-5): shown read-only. */
  linkedTaskTitle: string | null
}

export function TimeEntryTitleField({ control, disabled = false, linkedTaskTitle }: TitleFieldProps) {
  const { t } = useTranslation()
  const isTaskLinked = linkedTaskTitle !== null

  return (
    <MetaField control={control} name="title" metaKey="title" label={t('timeEntries.form.title')} required={!isTaskLinked}>
      {({ field, disabled: fieldDisabled }) => (
        <FormControl>
          <Input
            value={isTaskLinked ? linkedTaskTitle : field.value}
            onChange={(event) => field.onChange(event.target.value)}
            onBlur={field.onBlur}
            name={field.name}
            disabled={fieldDisabled || disabled || isTaskLinked}
            readOnly={isTaskLinked}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function TimeEntryDateField({ control, disabled = false }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="date" metaKey="date" label={t('timeEntries.form.date')} required>
      {({ field, disabled: fieldDisabled }) => (
        <FormControl>
          <Input
            type="date"
            value={field.value}
            onChange={(event) => field.onChange(event.target.value)}
            onBlur={field.onBlur}
            name={field.name}
            disabled={fieldDisabled || disabled}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function TimeEntryTypeField({ control, disabled = false }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="task_type_id" metaKey="task_type_id" label={t('timeEntries.form.type')} required>
      {({ field, disabled: fieldDisabled }) => (
        <FormControl>
          <TimeEntryTypePicker
            value={field.value}
            onChange={(id) => field.onChange(id)}
            disabled={fieldDisabled || disabled}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

interface ScheduleFieldsProps extends FieldProps {
  /** Both recompute "Tempo" from the two times (spec 0122 AC-029). */
  onStartTimeChange: (value: string) => void
  onEndTimeChange: (value: string) => void
}

/** "Dalle ore", "Alle ore" and "Tempo": one group, since the time drives the minutes. */
export function TimeEntryScheduleFields({ control, disabled = false, onStartTimeChange, onEndTimeChange }: ScheduleFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="grid min-w-0 gap-4 md:grid-cols-3">
      <MetaField control={control} name="start_time" metaKey="start_time" label={t('timeEntries.form.startTime')}>
        {({ field, disabled: fieldDisabled }) => (
          <FormControl>
            <Input
              type="time"
              value={field.value ?? ''}
              onChange={(event) => onStartTimeChange(event.target.value)}
              onBlur={field.onBlur}
              name={field.name}
              disabled={fieldDisabled || disabled}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="end_time" metaKey="end_time" label={t('timeEntries.form.endTime')}>
        {({ field, disabled: fieldDisabled }) => (
          <FormControl>
            <Input
              type="time"
              value={field.value ?? ''}
              onChange={(event) => onEndTimeChange(event.target.value)}
              onBlur={field.onBlur}
              name={field.name}
              disabled={fieldDisabled || disabled}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField control={control} name="minutes" metaKey="minutes" label={t('timeEntries.form.minutes')} required>
        {({ field, disabled: fieldDisabled }) => (
          <FormControl>
            <Input
              type="time"
              value={minutesToTimeValue(field.value ?? 0)}
              onChange={(event) => field.onChange(timeValueToMinutes(event.target.value) ?? 0)}
              onBlur={field.onBlur}
              name={field.name}
              disabled={fieldDisabled || disabled}
            />
          </FormControl>
        )}
      </MetaField>
    </div>
  )
}

export function TimeEntryNotesField({ control, disabled = false }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="notes" metaKey="notes" label={t('timeEntries.form.notes')}>
      {({ field, disabled: fieldDisabled }) => (
        <FormControl>
          <Textarea
            className="min-h-28"
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value === '' ? null : event.target.value)}
            onBlur={field.onBlur}
            name={field.name}
            disabled={fieldDisabled || disabled}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
