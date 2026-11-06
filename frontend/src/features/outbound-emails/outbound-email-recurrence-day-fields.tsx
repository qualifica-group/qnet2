import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { cn } from '@/lib/utils'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { monthName, ordinalLabel, weekdayInitial } from '@/features/tasks/task-recurrence-format'
import { numberInputProps } from '@/features/tasks/task-recurrence-field-props'
import { WEEKDAY_KEYS, WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import { TASK_RECURRENCE_MONTH_MODES } from '@/features/tasks/task-recurrence-types'
import type { ComposerFormValues } from '@/features/outbound-emails/outbound-email-schema'

const ORDINAL_CHOICES = [1, 2, 3, 4, 5] as const
const YEAR_MONTH_CHOICES = Array.from({ length: 12 }, (_value, index) => index + 1)

const DAY_CLASS =
  'flex size-7 items-center justify-center rounded-full text-xs font-medium outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50'
const DAY_SELECTED_CLASS = 'bg-primary text-primary-foreground shadow-sm'
const DAY_IDLE_CLASS = 'border border-field-border bg-card text-muted-foreground hover:bg-accent hover:text-foreground'

function capitalize(value: string): string {
  return value.length === 0 ? value : value[0].toUpperCase() + value.slice(1)
}

interface RecurrenceDayFieldsProps {
  form: UseFormReturn<ComposerFormValues>
  disabled: boolean
}

/** Weekly rule: one round checkbox per ISO weekday, named by the full day for assistive tech. */
export function OutboundEmailRecurrenceWeekdays({ form, disabled }: RecurrenceDayFieldsProps) {
  const { t, i18n } = useTranslation()
  const groupLabel = t('tasks.form.recurrence.weekdays')

  return (
    <FormField
      control={form.control}
      name="recurrence.weekdays"
      render={({ field }) => (
        <FormItem>
          <FormLabel>{groupLabel}</FormLabel>
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
                  onClick={() => field.onChange(checked ? field.value.filter((value) => value !== day) : [...field.value, day])}
                >
                  {weekdayInitial(day, i18n.language)}
                </button>
              )
            })}
          </div>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}

/** Monthly/yearly rule: fixed day or "nth weekday", plus the calendar month for a yearly one. */
export function OutboundEmailRecurrenceMonthFields({
  form,
  disabled,
  yearly,
}: RecurrenceDayFieldsProps & { yearly: boolean }) {
  const { t, i18n } = useTranslation()
  const isOrdinal = form.watch('recurrence.month_mode') === 'ordinal'
  const clearOptions = { shouldDirty: true } as const

  const modeOptions = TASK_RECURRENCE_MONTH_MODES.map((mode) => ({
    value: mode,
    label: t(`tasks.form.recurrence.monthModeOption.${mode}`),
  }))

  const numberSelect = (
    name: 'recurrence.ordinal' | 'recurrence.ordinal_weekday' | 'recurrence.year_month',
    label: string,
    choices: readonly { value: number; label: string }[],
  ) => (
    <FormField
      control={form.control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
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
              {choices.map((choice) => (
                <SelectItem key={choice.value} value={String(choice.value)}>
                  {choice.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  )

  return (
    <div className="flex flex-col gap-3">
      <FormField
        control={form.control}
        name="recurrence.month_mode"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('tasks.form.recurrence.monthMode')}</FormLabel>
            <FormControl>
              <SegmentedControl
                aria-label={t('tasks.form.recurrence.monthMode')}
                value={field.value ?? 'fixed'}
                options={modeOptions}
                disabled={disabled}
                onValueChange={(next) => {
                  field.onChange(next)
                  form.setValue(next === 'fixed' ? 'recurrence.ordinal' : 'recurrence.month_day', null, clearOptions)
                  if (next === 'fixed') {
                    form.setValue('recurrence.ordinal_weekday', null, clearOptions)
                  }
                }}
              />
            </FormControl>
          </FormItem>
        )}
      />

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {isOrdinal ? (
          <>
            {numberSelect(
              'recurrence.ordinal',
              t('tasks.form.recurrence.ordinal'),
              ORDINAL_CHOICES.map((value) => ({ value, label: ordinalLabel(value, i18n.language) })),
            )}
            {numberSelect(
              'recurrence.ordinal_weekday',
              t('tasks.form.recurrence.ordinalWeekday'),
              WEEKDAY_ORDER.map((value, index) => ({
                value,
                label: t(`tasks.form.recurrence.weekday.${WEEKDAY_KEYS[index]}`),
              })),
            )}
          </>
        ) : (
          <FormField
            control={form.control}
            name="recurrence.month_day"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('tasks.form.recurrence.monthDay')}</FormLabel>
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
                <FormMessage />
              </FormItem>
            )}
          />
        )}
        {yearly
          ? numberSelect(
              'recurrence.year_month',
              t('tasks.form.recurrence.yearMonth'),
              YEAR_MONTH_CHOICES.map((value) => ({ value, label: capitalize(monthName(value, i18n.language)) })),
            )
          : null}
      </div>
    </div>
  )
}
