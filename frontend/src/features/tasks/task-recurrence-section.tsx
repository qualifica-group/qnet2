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
import { TaskRecurrenceWeekdaysField } from '@/features/tasks/task-recurrence-weekdays-field'
import { TASK_RECURRENCE_END_MODES, TASK_RECURRENCE_FREQUENCIES } from '@/features/tasks/types'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceEndMode, TaskRecurrenceFrequency } from '@/features/tasks/types'

/** The one protected field (spec 0120 D-12) every control below shares. */
const RECURRENCE_META_KEY = 'recurrence'

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

/**
 * "Ricorrenza" (spec 0120 D-1/D-12): every control below reads the SAME
 * `metaKey`, so an actor without the mandate sees the whole section locked at
 * once (AC-034) through the ordinary `MetaField` mechanism — no bespoke
 * read-only rendering.
 *
 * AC-032: the master switch alone turns the section on; the fields that
 * follow track the picked frequency/ends, and switching either clears —
 * right in the `onValueChange` handler, never in an effect — whatever no
 * longer applies, so a stale value can never reach the payload builder.
 */
export function TaskRecurrenceSection({ control }: TaskRecurrenceSectionProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<TaskFormValues>()
  const { field: fieldPermission } = useResourcePermissions()

  const permission = fieldPermission(RECURRENCE_META_KEY)
  const frequency = useWatch({ control, name: 'recurrence.frequency' })
  const ends = useWatch({ control, name: 'recurrence.ends' })
  const enabled = useWatch({ control, name: 'recurrence.enabled' })

  if (!permission.visible) {
    return null
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
                  // AC-032: only the newly-picked frequency's own field survives.
                  setValue('recurrence.weekdays', [], { shouldDirty: true })
                  setValue('recurrence.month_day', null, { shouldDirty: true })
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

          {frequency === 'monthly' ? (
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
