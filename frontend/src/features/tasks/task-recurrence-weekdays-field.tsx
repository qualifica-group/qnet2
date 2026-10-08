import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { cn } from '@/lib/utils'
import { MetaField } from '@/features/authorization/MetaField'
import { weekdayInitial } from '@/features/tasks/task-recurrence-format'
import { WEEKDAY_KEYS, WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/** The same week strip the read tile shows, made pressable: picked days filled (fill, not colour alone). */
const DAY_CLASS =
  'flex size-7 items-center justify-center rounded-full text-xs font-medium outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50'
const DAY_SELECTED_CLASS = 'bg-primary text-primary-foreground shadow-sm'
const DAY_IDLE_CLASS = 'border border-field-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground'

interface TaskRecurrenceWeekdaysFieldProps {
  control: Control<TaskFormValues>
  /** The single protected field (spec 0120 D-12) every control of the section shares. */
  metaKey: string
}

/**
 * The weekly frequency's own control (spec 0120 D-1): one round toggle per
 * day bound to `recurrence.weekdays`, showing the locale's day initial and
 * named by the full day for assistive tech. Each toggle is a checkbox (any
 * number of days can be picked), so the group has no single focusable root
 * and renders without a `<FormControl>` wrapper; the label and the error
 * stay wired through `MetaField`'s own `<FormItem>`/`<FormMessage>`.
 */
export function TaskRecurrenceWeekdaysField({ control, metaKey }: TaskRecurrenceWeekdaysFieldProps) {
  const { t, i18n } = useTranslation()
  const groupLabel = t('tasks.form.recurrence.weekdays')

  return (
    <MetaField control={control} name="recurrence.weekdays" metaKey={metaKey} label={groupLabel}>
      {({ field, disabled }) => (
        <div role="group" aria-label={groupLabel} className="flex flex-wrap gap-1.5">
          {WEEKDAY_ORDER.map((day, index) => {
            const checked = field.value.includes(day)
            return (
              <button
                key={day}
                type="button"
                role="checkbox"
                aria-checked={checked}
                aria-label={t(`tasks.form.recurrence.weekday.${WEEKDAY_KEYS[index]}`)}
                disabled={disabled}
                className={cn(DAY_CLASS, checked ? DAY_SELECTED_CLASS : DAY_IDLE_CLASS)}
                onClick={() =>
                  field.onChange(checked ? field.value.filter((value) => value !== day) : [...field.value, day])
                }
              >
                {weekdayInitial(day, i18n.language)}
              </button>
            )
          })}
        </div>
      )}
    </MetaField>
  )
}
