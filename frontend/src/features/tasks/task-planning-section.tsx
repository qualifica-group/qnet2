import { useTranslation } from 'react-i18next'
import { CalendarClock } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/** A date paired with its time, rendered side by side (D-11: still two distinct columns). */
const DATE_TIME_PAIRS = [
  { date: 'start_date', time: 'start_time' },
  { date: 'end_date', time: 'end_time' },
] as const


/** Date input + narrow time input on one row, so "when" reads as one thing. */
const DATE_TIME_ROW_CLASS = 'grid min-w-0 grid-cols-[minmax(0,1fr)_7.5rem] items-start gap-2'

/** Camel-case i18n leaf for a snake_case field key (`start_date` -> `startDate`). */
function labelKeyOf(field: string): string {
  return field.replace(/_([a-z])/g, (_match, letter: string) => letter.toUpperCase())
}

interface TaskPlanningSectionProps {
  control: Control<TaskFormValues>
}

/**
 * "Pianificazione": start and end (each a date plus its time) and the estimate.
 * `completion_date` has no control here: it is set when the task is completed
 * (Completa action), so the form never offers it.
 *
 * D-11: dates and times are DISTINCT columns, never fused into a datetime — a
 * task may carry a time without a date and vice versa — and the estimate is
 * plain minutes, with no implicit "2h30" parsing. No ordering constraint is
 * imposed between the dates: none was requested, and inventing one silently
 * would be a business rule the backend does not enforce either.
 *
 * NOTE (D-11): `FieldDefinition` has no `time` type, so the backend declares
 * `start_time`/`end_time` as `text` validated `date_format:H:i`. A native
 * `type="time"` input produces exactly that, so the control matches the
 * server format without a converter.
 */
export function TaskPlanningSection({ control }: TaskPlanningSectionProps) {
  const { t } = useTranslation()

  const renderDateField = (name: (typeof DATE_TIME_PAIRS)[number]['date']) => (
    <MetaField key={name} control={control} name={name} metaKey={name} label={t(`tasks.form.${labelKeyOf(name)}`)}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="date"
            disabled={disabled}
            readOnly={readOnly}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )

  const renderTimeField = (name: (typeof DATE_TIME_PAIRS)[number]['time']) => (
    <MetaField key={name} control={control} name={name} metaKey={name} label={t(`tasks.form.${labelKeyOf(name)}`)}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="time"
            disabled={disabled}
            readOnly={readOnly}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )

  return (
    <FormSection
      icon={CalendarClock}
      title={t('tasks.form.sections.planning.title')}
      description={t('tasks.form.sections.planning.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        {DATE_TIME_PAIRS.map((pair) => (
          <div key={pair.date} className={DATE_TIME_ROW_CLASS}>
            {renderDateField(pair.date)}
            {renderTimeField(pair.time)}
          </div>
        ))}

        <MetaField
          control={control}
          name="estimated_minutes"
          metaKey="estimated_minutes"
          label={t('tasks.form.estimatedMinutes')}
          description={t('tasks.form.estimatedMinutesHint')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                min={0}
                step={1}
                inputMode="numeric"
                disabled={disabled}
                readOnly={readOnly}
                value={field.value ?? ''}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>
      </div>
    </FormSection>
  )
}
