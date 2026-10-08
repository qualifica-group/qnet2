import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { MetaField } from '@/features/authorization/MetaField'
import {
  numberInputProps,
  RECURRENCE_META_KEY,
  RECURRENCE_RESET_OPTIONS,
} from '@/features/tasks/task-recurrence-field-props'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskRecurrenceEndMode } from '@/features/tasks/types'

/** Reading order of the end modes: open-ended first, then the two bounded ones. */
const END_MODE_ORDER = ['never', 'on_date', 'after_count'] as const satisfies readonly TaskRecurrenceEndMode[]

/**
 * When the series stops (spec 0120 D-1): a segmented Mai / A una data /
 * Dopo N occorrenze, then the picked mode's own value. AC-032: a change of
 * mode clears the other mode's value right in the handler.
 */
export function TaskRecurrenceEndFields({ control }: { control: Control<TaskFormValues> }) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<TaskFormValues>()
  const ends = useWatch({ control, name: 'recurrence.ends' })

  const options = END_MODE_ORDER.map((mode) => ({
    value: mode,
    label: t(`tasks.form.recurrence.endsOption.${mode}`),
  }))

  return (
    <div className="flex flex-col gap-3">
      <MetaField control={control} name="recurrence.ends" metaKey={RECURRENCE_META_KEY} label={t('tasks.form.recurrence.ends')}>
        {({ field, disabled }) => (
          <FormControl>
            <SegmentedControl
              aria-label={t('tasks.form.recurrence.ends')}
              value={field.value}
              options={options}
              disabled={disabled}
              onValueChange={(next) => {
                field.onChange(next)
                setValue('recurrence.ends_on', null, RECURRENCE_RESET_OPTIONS)
                setValue('recurrence.occurrence_count', null, RECURRENCE_RESET_OPTIONS)
              }}
            />
          </FormControl>
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
                className="w-full max-w-44"
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
            <div className="flex items-center gap-2">
              <FormControl>
                <Input
                  type="number"
                  min={1}
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
              <span className="text-sm text-muted-foreground">
                {t('tasks.form.recurrence.occurrenceUnit', { count: field.value ?? 0 })}
              </span>
            </div>
          )}
        </MetaField>
      ) : null}
    </div>
  )
}
