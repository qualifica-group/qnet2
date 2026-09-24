import { useTranslation } from 'react-i18next'
import { Repeat } from 'lucide-react'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ordinalLabel, monthName } from '@/features/tasks/task-recurrence-format'
import { TaskRecurrenceWeekdaysField } from '@/features/tasks/task-recurrence-weekdays-field'
import { WEEKDAY_KEYS, WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import { TASK_RECURRENCE_END_MODES, TASK_RECURRENCE_FREQUENCIES } from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type {
  TaskRecurrenceEndMode,
  TaskRecurrenceFrequency,
  TaskRecurrenceMonthMode,
} from '@/features/tasks/types'

/** The one protected field (spec 0120 D-12) every control below shares. */
const RECURRENCE_META_KEY = 'recurrence'

/** Spec 0155 D-1: the ordinal picker's own 1..5 range ("2nd Tuesday", ordinal 5 skips a month without one). */
const ORDINAL_CHOICES = [1, 2, 3, 4, 5] as const
/** Spec 0155 D-1: the yearly picker's own calendar months, 1 (January) through 12 (December). */
const YEAR_MONTH_CHOICES = Array.from({ length: 12 }, (_value, index) => index + 1)

interface TaskRecurrenceSectionProps {
  control: Control<TaskFormValues>
}

/** Bare `<Input type="number">`, min-1, tolerating a blank box while typing (mirrors `TaskPlanningSection`). */
function numberInputProps(value: number | null, onChange: (next: number | null) => void) {
  return {
    value: value ?? '',
    onChange: (event: React.ChangeEvent<HTMLInputElement>) =>
      onChange(event.target.value === '' ? null : Number(event.target.value)),
  }
}

/** First letter capitalized, for a picker's option label — `monthName` itself stays lowercase for the sentence form. */
function capitalize(value: string): string {
  return value.length === 0 ? value : value[0].toUpperCase() + value.slice(1)
}

/**
 * "Ricorrenza" (spec 0120 D-1/D-12, spec 0155 D-1): every control below reads
 * the SAME `metaKey`, so an actor without the mandate sees the whole section
 * locked at once (AC-034) through the ordinary `MetaField` mechanism — no
 * bespoke read-only rendering.
 *
 * AC-032: the master switch alone turns the section on; the fields that
 * follow track the picked frequency/month_mode/ends, and switching any of
 * them clears — right in the `onValueChange` handler, never in an effect —
 * whatever no longer applies, so a stale value can never reach the payload
 * builder.
 *
 * Spec 0155 D-1 adds `yearly`/`custom` frequencies, the monthly/yearly
 * fixed/ordinal day-of-month split, the yearly calendar month and a general
 * "solo giorni lavorativi" switch. `month_mode` defaults to `fixed` the
 * instant monthly/yearly is picked (mirrors the master switch's own D-1
 * seeding), so the plain day-of-month input is the one an operator sees
 * first — the ordinal picker is the deliberate extra step.
 */
export function TaskRecurrenceSection({ control }: TaskRecurrenceSectionProps) {
  const { t, i18n } = useTranslation()
  const { setValue } = useFormContext<TaskFormValues>()
  const { field: fieldPermission } = useResourcePermissions()

  const permission = fieldPermission(RECURRENCE_META_KEY)
  const frequency = useWatch({ control, name: 'recurrence.frequency' })
  const monthMode = useWatch({ control, name: 'recurrence.month_mode' })
  const ends = useWatch({ control, name: 'recurrence.ends' })
  const enabled = useWatch({ control, name: 'recurrence.enabled' })

  if (!permission.visible) {
    return null
  }

  const isMonthlyOrYearly = frequency === 'monthly' || frequency === 'yearly'

  /** Spec 0155 D-1: every frequency-specific field resets on a frequency change — only the newly-picked one survives. */
  function resetFrequencyFields(next: TaskRecurrenceFrequency) {
    setValue('recurrence.weekdays', [], { shouldDirty: true })
    const nextIsMonthlyOrYearly = next === 'monthly' || next === 'yearly'
    setValue('recurrence.month_mode', nextIsMonthlyOrYearly ? 'fixed' : null, { shouldDirty: true })
    setValue('recurrence.month_day', null, { shouldDirty: true })
    setValue('recurrence.ordinal', null, { shouldDirty: true })
    setValue('recurrence.ordinal_weekday', null, { shouldDirty: true })
    setValue('recurrence.year_month', null, { shouldDirty: true })
  }

  /** Spec 0155 D-1: switching fixed<->ordinal clears the other branch's own fields. */
  function resetMonthModeFields(next: TaskRecurrenceMonthMode) {
    if (next === 'fixed') {
      setValue('recurrence.ordinal', null, { shouldDirty: true })
      setValue('recurrence.ordinal_weekday', null, { shouldDirty: true })
    } else {
      setValue('recurrence.month_day', null, { shouldDirty: true })
    }
  }

  return (
    <FormSection
      icon={Repeat}
      title={t('tasks.form.sections.recurrence.title')}
      description={t('tasks.form.sections.recurrence.description')}
    >
      <MetaField
        control={control}
        name="recurrence.enabled"
        metaKey={RECURRENCE_META_KEY}
        label={t('tasks.form.recurrence.enable')}
        description={t('tasks.form.recurrence.enableHint')}
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
                  setValue('recurrence.frequency', 'daily', { shouldDirty: true })
                  setValue('recurrence.ends', 'never', { shouldDirty: true })
                }
              }}
            />
          </FormControl>
        )}
      </MetaField>

      {enabled ? (
        <div className={FIELD_GRID_CLASS}>
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
            description={t('tasks.form.recurrence.intervalHint')}
          >
            {({ field, disabled }) => (
              <FormControl>
                <Input
                  type="number"
                  min={1}
                  step={1}
                  inputMode="numeric"
                  disabled={disabled}
                  onBlur={field.onBlur}
                  name={field.name}
                  ref={field.ref}
                  {...numberInputProps(field.value, field.onChange)}
                />
              </FormControl>
            )}
          </MetaField>

          {frequency === 'weekly' ? (
            <TaskRecurrenceWeekdaysField
              control={control}
              metaKey={RECURRENCE_META_KEY}
              className="@2xl:col-span-2"
            />
          ) : null}

          {isMonthlyOrYearly ? (
            <MetaField
              control={control}
              name="recurrence.month_mode"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.monthMode')}
            >
              {({ field, disabled }) => (
                <Select
                  value={field.value ?? 'fixed'}
                  disabled={disabled}
                  onValueChange={(next) => {
                    field.onChange(next as TaskRecurrenceMonthMode)
                    resetMonthModeFields(next as TaskRecurrenceMonthMode)
                  }}
                >
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="fixed">{t('tasks.form.recurrence.monthModeOption.fixed')}</SelectItem>
                    <SelectItem value="ordinal">{t('tasks.form.recurrence.monthModeOption.ordinal')}</SelectItem>
                  </SelectContent>
                </Select>
              )}
            </MetaField>
          ) : null}

          {isMonthlyOrYearly && monthMode !== 'ordinal' ? (
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
                    disabled={disabled}
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                    {...numberInputProps(field.value, field.onChange)}
                  />
                </FormControl>
              )}
            </MetaField>
          ) : null}

          {isMonthlyOrYearly && monthMode === 'ordinal' ? (
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
          ) : null}

          {isMonthlyOrYearly && monthMode === 'ordinal' ? (
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
          ) : null}

          {frequency === 'yearly' ? (
            <MetaField
              control={control}
              name="recurrence.year_month"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.yearMonth')}
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

          <MetaField
            control={control}
            name="recurrence.workdays_only"
            metaKey={RECURRENCE_META_KEY}
            label={t('tasks.form.recurrence.workdaysOnly')}
            description={t('tasks.form.recurrence.workdaysOnlyHint')}
            layout="inline"
            className="@2xl:col-span-2"
          >
            {({ field, disabled }) => (
              <FormControl>
                <Switch checked={field.value} disabled={disabled} onCheckedChange={field.onChange} />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={control}
            name="recurrence.ends"
            metaKey={RECURRENCE_META_KEY}
            label={t('tasks.form.recurrence.ends')}
          >
            {({ field, disabled }) => (
              <Select
                value={field.value ?? undefined}
                disabled={disabled}
                onValueChange={(next) => {
                  field.onChange(next as TaskRecurrenceEndMode)
                  // AC-032: only the newly-picked end mode's own field survives.
                  setValue('recurrence.ends_on', null, { shouldDirty: true })
                  setValue('recurrence.occurrence_count', null, { shouldDirty: true })
                }}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {TASK_RECURRENCE_END_MODES.map((option) => (
                    <SelectItem key={option} value={option}>
                      {t(`tasks.form.recurrence.endsOption.${option}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>

          {ends === 'on_date' ? (
            <MetaField
              control={control}
              name="recurrence.ends_on"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.endsOn')}
            >
              {({ field, disabled }) => (
                <FormControl>
                  <Input
                    type="date"
                    disabled={disabled}
                    value={field.value ?? ''}
                    onChange={(event) => field.onChange(event.target.value || null)}
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                  />
                </FormControl>
              )}
            </MetaField>
          ) : null}

          {ends === 'after_count' ? (
            <MetaField
              control={control}
              name="recurrence.occurrence_count"
              metaKey={RECURRENCE_META_KEY}
              label={t('tasks.form.recurrence.occurrenceCount')}
            >
              {({ field, disabled }) => (
                <FormControl>
                  <Input
                    type="number"
                    min={1}
                    step={1}
                    inputMode="numeric"
                    disabled={disabled}
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                    {...numberInputProps(field.value, field.onChange)}
                  />
                </FormControl>
              )}
            </MetaField>
          ) : null}
        </div>
      ) : null}
    </FormSection>
  )
}
