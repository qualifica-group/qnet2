import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { Repeat } from 'lucide-react'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { TaskRecurrenceDayFields } from '@/features/tasks/task-recurrence-day-fields'
import { TaskRecurrenceEndFields } from '@/features/tasks/task-recurrence-end-fields'
import {
  intervalUnitKey,
  numberInputProps,
  RECURRENCE_META_KEY,
  RECURRENCE_RESET_OPTIONS,
} from '@/features/tasks/task-recurrence-field-props'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { recurrencePreviewRule } from '@/features/tasks/task-recurrence-preview'
import { TaskRecurrenceWeekdaysField } from '@/features/tasks/task-recurrence-weekdays-field'
import { TASK_RECURRENCE_FREQUENCIES } from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceFrequency } from '@/features/tasks/types'

interface TaskRecurrenceSectionProps {
  control: Control<TaskFormValues>
}

/**
 * The rule as one sentence while it is being drafted — the same phrase the
 * read tile and the header badge show — or a prompt while a field is
 * missing. Polite live region: it follows every control above it.
 */
function RecurrencePreview({ control }: TaskRecurrenceSectionProps) {
  const { t, i18n } = useTranslation()
  const recurrence = useWatch({ control, name: 'recurrence' })
  const rule = recurrencePreviewRule(recurrence)

  return (
    <div className="flex items-start gap-2.5 rounded-b-lg border-t border-primary/20 bg-primary/5 px-3 py-2.5" aria-live="polite">
      <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
        <Repeat className="size-3.5" aria-hidden="true" />
      </span>
      <div className="flex min-w-0 flex-col">
        <span className="text-xs font-semibold text-primary">{t('tasks.form.recurrence.preview')}</span>
        {rule ? (
          <p className="text-sm font-medium text-foreground">{formatTaskRecurrenceRule(rule, t, i18n.language)}</p>
        ) : (
          <p className="text-xs text-muted-foreground">{t('tasks.form.recurrence.previewIncomplete')}</p>
        )}
      </div>
    </div>
  )
}

/**
 * "Ricorrenza" (spec 0120 D-1/D-12, spec 0155 D-1): every control reads the
 * SAME `metaKey`, so an actor without the mandate sees the whole editor
 * locked at once (AC-034) through the ordinary `MetaField` mechanism.
 *
 * AC-032: the master switch alone turns the rule on; the fields that follow
 * track the picked frequency/month_mode/ends, and switching any of them
 * clears — right in the handler, never in an effect — whatever no longer
 * applies, so a stale value never reaches the payload builder.
 *
 * Layout: the switch, then one raised panel split in bands — how often
 * (frequency, "every N <unit>", the frequency's own day pickers), when it
 * ends, the workday shift — closed by the live sentence preview. The long
 * explanations live in info tooltips, not under the controls.
 */
export function TaskRecurrenceFields({ control }: TaskRecurrenceSectionProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<TaskFormValues>()
  const { field: fieldPermission } = useResourcePermissions()

  const permission = fieldPermission(RECURRENCE_META_KEY)
  const frequency = useWatch({ control, name: 'recurrence.frequency' })
  const enabled = useWatch({ control, name: 'recurrence.enabled' })

  if (!permission.visible) {
    return null
  }

  const isMonthlyOrYearly = frequency === 'monthly' || frequency === 'yearly'

  /** Spec 0155 D-1: every frequency-specific field resets on a frequency change — only the newly-picked one survives. */
  function resetFrequencyFields(next: TaskRecurrenceFrequency) {
    setValue('recurrence.weekdays', [], RECURRENCE_RESET_OPTIONS)
    const nextIsMonthlyOrYearly = next === 'monthly' || next === 'yearly'
    setValue('recurrence.month_mode', nextIsMonthlyOrYearly ? 'fixed' : null, RECURRENCE_RESET_OPTIONS)
    setValue('recurrence.month_day', null, RECURRENCE_RESET_OPTIONS)
    setValue('recurrence.ordinal', null, RECURRENCE_RESET_OPTIONS)
    setValue('recurrence.ordinal_weekday', null, RECURRENCE_RESET_OPTIONS)
    setValue('recurrence.year_month', null, RECURRENCE_RESET_OPTIONS)
  }

  return (
    <div className="flex flex-col gap-3">
      <MetaField
        control={control}
        name="recurrence.enabled"
        metaKey={RECURRENCE_META_KEY}
        label={t('tasks.form.recurrence.enable')}
        hint={t('tasks.form.recurrence.enableHint')}
        layout="inline"
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch
              checked={field.value}
              disabled={disabled}
              onCheckedChange={(next) => {
                field.onChange(next)
                // First activation: seed the minimum D-1 needs for a valid
                // rule instead of leaving every picker below unset.
                if (next && frequency === null) {
                  setValue('recurrence.frequency', 'daily', RECURRENCE_RESET_OPTIONS)
                  setValue('recurrence.ends', 'never', RECURRENCE_RESET_OPTIONS)
                }
              }}
            />
          </FormControl>
        )}
      </MetaField>

      {enabled ? (
        <div className="flex min-w-0 flex-col rounded-lg border bg-card shadow-xs">
          <div className="flex flex-col gap-3 p-3">
            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
              <MetaField
                control={control}
                name="recurrence.frequency"
                metaKey={RECURRENCE_META_KEY}
                label={t('tasks.form.recurrence.frequency')}
              >
                {({ field, disabled }) => (
                  <Select
                    value={field.value ?? undefined}
                    disabled={disabled}
                    onValueChange={(next) => {
                      field.onChange(next as TaskRecurrenceFrequency)
                      resetFrequencyFields(next as TaskRecurrenceFrequency)
                    }}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {TASK_RECURRENCE_FREQUENCIES.map((option) => (
                        <SelectItem key={option} value={option}>
                          {t(`tasks.form.recurrence.frequencyOption.${option}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>

              <MetaField
                control={control}
                name="recurrence.interval"
                metaKey={RECURRENCE_META_KEY}
                label={t('tasks.form.recurrence.interval')}
                hint={t('tasks.form.recurrence.intervalHint')}
              >
                {({ field, disabled }) => (
                  <div className="flex items-center gap-2">
                    <FormControl>
                      <Input
                        type="number"
                        min={1}
                        step={1}
                        inputMode="numeric"
                        className="w-16"
                        disabled={disabled}
                        onBlur={field.onBlur}
                        name={field.name}
                        ref={field.ref}
                        {...numberInputProps(field.value, field.onChange)}
                      />
                    </FormControl>
                    <span className="text-sm text-muted-foreground">
                      {t(intervalUnitKey(frequency), { count: field.value ?? 0 })}
                    </span>
                  </div>
                )}
              </MetaField>
            </div>

            {frequency === 'weekly' ? (
              <TaskRecurrenceWeekdaysField control={control} metaKey={RECURRENCE_META_KEY} />
            ) : null}

            {isMonthlyOrYearly ? <TaskRecurrenceDayFields control={control} yearly={frequency === 'yearly'} /> : null}
          </div>

          <div className="border-t p-3">
            <TaskRecurrenceEndFields control={control} />
          </div>

          <div className="border-t p-3">
            <MetaField
              control={control}
              name="recurrence.workdays_only"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.workdaysOnly')}
              hint={t('tasks.form.recurrence.workdaysOnlyHint')}
              layout="inline"
            >
              {({ field, disabled }) => (
                <FormControl>
                  <Switch checked={field.value} disabled={disabled} onCheckedChange={field.onChange} />
                </FormControl>
              )}
            </MetaField>
          </div>

          <RecurrencePreview control={control} />
        </div>
      ) : null}
    </div>
  )
}
