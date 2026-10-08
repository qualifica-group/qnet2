import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { monthName, ordinalLabel } from '@/features/tasks/task-recurrence-format'
import {
  numberInputProps,
  RECURRENCE_META_KEY,
  RECURRENCE_RESET_OPTIONS,
} from '@/features/tasks/task-recurrence-field-props'
import { WEEKDAY_KEYS, WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import { TASK_RECURRENCE_MONTH_MODES } from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceMonthMode } from '@/features/tasks/types'

/** Spec 0155 D-1: the ordinal picker's own 1..5 range ("2nd Tuesday", ordinal 5 skips a month without one). */
const ORDINAL_CHOICES = [1, 2, 3, 4, 5] as const
/** Spec 0155 D-1: the yearly picker's own calendar months, 1 (January) through 12 (December). */
const YEAR_MONTH_CHOICES = Array.from({ length: 12 }, (_value, index) => index + 1)

/** First letter capitalized, for a picker's option label — `monthName` itself stays lowercase for the sentence form. */
function capitalize(value: string): string {
  return value.length === 0 ? value : value[0].toUpperCase() + value.slice(1)
}

interface TaskRecurrenceDayFieldsProps {
  control: Control<TaskFormValues>
  /** A yearly rule also picks its calendar month. */
  yearly: boolean
}

/**
 * The day a monthly/yearly rule falls on (spec 0155 D-1): a segmented
 * "Data fissa" / "Giorno della settimana" switch, then either the plain
 * day-of-month box or the ordinal + weekday pickers, plus the month for a
 * yearly rule. `month_mode` is seeded to `fixed` when the frequency is
 * picked, so the plain day box is what an operator sees first; switching
 * mode clears the other branch's fields right in the handler (AC-032).
 */
export function TaskRecurrenceDayFields({ control, yearly }: TaskRecurrenceDayFieldsProps) {
  const { t, i18n } = useTranslation()
  const { setValue } = useFormContext<TaskFormValues>()
  const monthMode = useWatch({ control, name: 'recurrence.month_mode' })
  const isOrdinal = monthMode === 'ordinal'

  function resetMonthModeFields(next: TaskRecurrenceMonthMode) {
    if (next === 'fixed') {
      setValue('recurrence.ordinal', null, RECURRENCE_RESET_OPTIONS)
      setValue('recurrence.ordinal_weekday', null, RECURRENCE_RESET_OPTIONS)
    } else {
      setValue('recurrence.month_day', null, RECURRENCE_RESET_OPTIONS)
    }
  }

  const modeOptions = TASK_RECURRENCE_MONTH_MODES.map((mode) => ({
    value: mode,
    label: t(`tasks.form.recurrence.monthModeOption.${mode}`),
  }))

  return (
    <div className="flex flex-col gap-3">
      <MetaField
        control={control}
        name="recurrence.month_mode"
        metaKey={RECURRENCE_META_KEY}
        label={t('tasks.form.recurrence.monthMode')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <SegmentedControl
              aria-label={t('tasks.form.recurrence.monthMode')}
              value={field.value ?? 'fixed'}
              options={modeOptions}
              disabled={disabled}
              onValueChange={(next) => {
                field.onChange(next)
                resetMonthModeFields(next)
              }}
            />
          </FormControl>
        )}
      </MetaField>

      <div className="grid grid-cols-2 gap-3">
        {isOrdinal ? (
          <>
            <MetaField
              control={control}
              name="recurrence.ordinal"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.ordinal')}
            >
              {({ field, disabled }) => (
                <Select
                  value={field.value !== null ? String(field.value) : undefined}
                  disabled={disabled}
                  onValueChange={(next) => field.onChange(Number(next))}
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {ORDINAL_CHOICES.map((choice) => (
                      <SelectItem key={choice} value={String(choice)}>
                        {ordinalLabel(choice, i18n.language)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </MetaField>
            <MetaField
              control={control}
              name="recurrence.ordinal_weekday"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.ordinalWeekday')}
            >
              {({ field, disabled }) => (
                <Select
                  value={field.value !== null ? String(field.value) : undefined}
                  disabled={disabled}
                  onValueChange={(next) => field.onChange(Number(next))}
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {WEEKDAY_ORDER.map((day, index) => (
                      <SelectItem key={day} value={String(day)}>
                        {t(`tasks.form.recurrence.weekday.${WEEKDAY_KEYS[index]}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </MetaField>
          </>
        ) : (
          <MetaField
            control={control}
            name="recurrence.month_day"
            metaKey={RECURRENCE_META_KEY}
            label={t('tasks.form.recurrence.monthDay')}
          >
            {({ field, disabled }) => (
              <FormControl>
                <Input
                  type="number"
                  min={1}
                  max={31}
                  step={1}
                  inputMode="numeric"
                  className="w-20"
                  disabled={disabled}
                  onBlur={field.onBlur}
                  name={field.name}
                  ref={field.ref}
                  {...numberInputProps(field.value, field.onChange)}
                />
              </FormControl>
            )}
          </MetaField>
        )}

        {yearly ? (
          <MetaField
            control={control}
            name="recurrence.year_month"
            metaKey={RECURRENCE_META_KEY}
            label={t('tasks.form.recurrence.yearMonth')}
            className={isOrdinal ? 'col-span-2' : undefined}
          >
            {({ field, disabled }) => (
              <Select
                value={field.value !== null ? String(field.value) : undefined}
                disabled={disabled}
                onValueChange={(next) => field.onChange(Number(next))}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {YEAR_MONTH_CHOICES.map((choice) => (
                    <SelectItem key={choice} value={String(choice)}>
                      {capitalize(monthName(choice, i18n.language))}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        ) : null}
      </div>
    </div>
  )
}
