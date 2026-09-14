import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { MetaField } from '@/features/authorization/MetaField'
import type { TaskFormValues } from '@/features/tasks/task-schema'

/** ISO-8601 order (Monday=1..Sunday=7), shared with `task-recurrence-format.ts`. */
const WEEKDAY_ORDER = [1, 2, 3, 4, 5, 6, 7] as const
const WEEKDAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const

interface TaskRecurrenceWeekdaysFieldProps {
  control: Control<TaskFormValues>
  /** The single protected field (spec 0120 D-12) every control of the section shares. */
  metaKey: string
  className?: string
}

/**
 * The weekly frequency's own control (spec 0120 D-1): a compact row of day
 * checkboxes bound to `recurrence.weekdays`. A checkbox group has no single
 * focusable root, so — unlike every other control in this form — it renders
 * `MetaField`'s children WITHOUT a `<FormControl>` wrapper: there is no one
 * element for that Slot to attach to; the group-level label/error stay wired
 * through `MetaField`'s own `<FormItem>`/`<FormMessage>`.
 */
export function TaskRecurrenceWeekdaysField({
  control,
  metaKey,
  className,
}: TaskRecurrenceWeekdaysFieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="recurrence.weekdays"
      metaKey={metaKey}
      label={t('tasks.form.recurrence.weekdays')}
      className={className}
    >
      {({ field, disabled }) => (
        <div className="flex flex-wrap gap-3">
          {WEEKDAY_ORDER.map((day, index) => {
            const id = `task-recurrence-weekday-${WEEKDAY_KEYS[index]}`
            return (
              <div key={day} className="flex items-center gap-1.5">
                <Checkbox
                  id={id}
                  checked={field.value.includes(day)}
                  disabled={disabled}
                  onCheckedChange={(checked) => {
                    const next =
                      checked === true
                        ? [...field.value, day]
                        : field.value.filter((value) => value !== day)
                    field.onChange(next)
                  }}
                />
                <Label htmlFor={id} className="text-xs font-normal">
                  {t(`tasks.form.recurrence.weekday.${WEEKDAY_KEYS[index]}`)}
                </Label>
              </div>
            )
          })}
        </div>
      )}
    </MetaField>
  )
}
