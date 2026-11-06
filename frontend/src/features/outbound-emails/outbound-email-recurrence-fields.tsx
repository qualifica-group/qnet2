import { useTranslation } from 'react-i18next'
import { Repeat } from 'lucide-react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { intervalUnitKey, numberInputProps } from '@/features/tasks/task-recurrence-field-props'
import type { TaskRecurrenceFrequency } from '@/features/tasks/task-recurrence-types'
import {
  OutboundEmailRecurrenceMonthFields,
  OutboundEmailRecurrenceWeekdays,
} from '@/features/outbound-emails/outbound-email-recurrence-day-fields'
import { previewRule, todayIso } from '@/features/outbound-emails/outbound-email-recurrence-schema'
import type { ComposerFormValues } from '@/features/outbound-emails/outbound-email-schema'

/** `custom` is "every N days" (same as daily), so the picker offers the four natural choices. */
const FREQUENCY_CHOICES = ['daily', 'weekly', 'monthly', 'yearly'] as const satisfies readonly TaskRecurrenceFrequency[]
const CLEAR_OPTIONS = { shouldDirty: true } as const

interface OutboundEmailRecurrenceFieldsProps {
  form: UseFormReturn<ComposerFormValues>
  disabled: boolean
}

/** The rule as one sentence while it is drafted, or a prompt while something is missing. */
function RecurrencePreview({ form }: { form: UseFormReturn<ComposerFormValues> }) {
  const { t, i18n } = useTranslation()
  const recurrence = useWatch({ control: form.control, name: 'recurrence' })
  const rule = previewRule(recurrence)

  return (
    <div className="flex items-start gap-2 rounded-b-lg border-t bg-surface px-3 py-2" aria-live="polite">
      <Repeat className="mt-0.5 size-3.5 shrink-0 text-primary" aria-hidden="true" />
      <div className="flex min-w-0 flex-col">
        <span className="text-xs font-semibold text-primary">{t('outboundEmails.recurrence.preview')}</span>
        {rule ? (
          <p className="text-sm font-medium break-words">{formatTaskRecurrenceRule(rule, t, i18n.language)}</p>
        ) : (
          <p className="text-xs text-muted-foreground">{t('outboundEmails.recurrence.previewIncomplete')}</p>
        )}
      </div>
    </div>
  )
}

/**
 * "Ricorrenza" section of the composer (spec 0362): the switch alone turns
 * the series on; off, the composer is exactly the plain one. A change of
 * frequency clears, in the handler, whatever the new one no longer uses.
 * The end date is mandatory (D-4) and every error is wired to its field
 * (`aria-invalid` + `aria-describedby` + `role="alert"` via `FormControl`).
 */
export function OutboundEmailRecurrenceFields({ form, disabled }: OutboundEmailRecurrenceFieldsProps) {
  const { t } = useTranslation()
  const enabled = useWatch({ control: form.control, name: 'recurrence.enabled' })
  const frequency = useWatch({ control: form.control, name: 'recurrence.frequency' })
  const today = todayIso()

  const resetFrequencyFields = (next: TaskRecurrenceFrequency) => {
    const monthly = next === 'monthly' || next === 'yearly'
    form.setValue('recurrence.weekdays', [], CLEAR_OPTIONS)
    form.setValue('recurrence.month_mode', monthly ? 'fixed' : null, CLEAR_OPTIONS)
    form.setValue('recurrence.month_day', null, CLEAR_OPTIONS)
    form.setValue('recurrence.ordinal', null, CLEAR_OPTIONS)
    form.setValue('recurrence.ordinal_weekday', null, CLEAR_OPTIONS)
    form.setValue('recurrence.year_month', null, CLEAR_OPTIONS)
  }

  return (
    <Form {...form}>
      <section className="flex flex-col gap-3" aria-label={t('outboundEmails.recurrence.title')}>
        <FormField
          control={form.control}
          name="recurrence.enabled"
          render={({ field }) => (
            <FormItem className="flex-row items-center justify-between gap-3 rounded-lg border bg-card px-3 py-2 shadow-xs">
              <div className="flex min-w-0 flex-col gap-0.5">
                <FormLabel className="flex items-center gap-1.5">
                  <Repeat className="size-3.5 text-muted-foreground" aria-hidden="true" />
                  {t('outboundEmails.recurrence.enable')}
                </FormLabel>
                <FormDescription className="text-xs">{t('outboundEmails.recurrence.enableHint')}</FormDescription>
              </div>
              <FormControl>
                <Switch checked={field.value} disabled={disabled} onCheckedChange={field.onChange} />
              </FormControl>
            </FormItem>
          )}
        />

        {enabled ? (
          <div className="flex min-w-0 flex-col rounded-lg border bg-card shadow-xs">
            <div className="flex flex-col gap-3 p-3">
              <div className="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <FormField
                  control={form.control}
                  name="recurrence.frequency"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('tasks.form.recurrence.frequency')}</FormLabel>
                      <Select
                        value={field.value}
                        disabled={disabled}
                        onValueChange={(next) => {
                          field.onChange(next)
                          resetFrequencyFields(next as TaskRecurrenceFrequency)
                        }}
                      >
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {FREQUENCY_CHOICES.map((option) => (
                            <SelectItem key={option} value={option}>
                              {t(`tasks.form.recurrence.frequencyOption.${option}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="recurrence.interval"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('tasks.form.recurrence.interval')}</FormLabel>
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
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              {frequency === 'weekly' ? <OutboundEmailRecurrenceWeekdays form={form} disabled={disabled} /> : null}
              {frequency === 'monthly' || frequency === 'yearly' ? (
                <OutboundEmailRecurrenceMonthFields form={form} disabled={disabled} yearly={frequency === 'yearly'} />
              ) : null}
            </div>

            <div className="grid grid-cols-1 gap-3 border-t p-3 sm:grid-cols-2">
              {(['starts_on', 'ends_on'] as const).map((name) => (
                <FormField
                  key={name}
                  control={form.control}
                  name={`recurrence.${name}`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel required>{t(`outboundEmails.recurrence.${name}`)}</FormLabel>
                      <FormControl>
                        <Input
                          type="date"
                          min={today}
                          className="w-full max-w-44"
                          disabled={disabled}
                          value={field.value ?? ''}
                          onChange={(event) => field.onChange(event.target.value || null)}
                          onBlur={field.onBlur}
                          name={field.name}
                          ref={field.ref}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ))}
            </div>

            <div className="border-t p-3">
              <FormField
                control={form.control}
                name="recurrence.workdays_only"
                render={({ field }) => (
                  <FormItem className="flex-row items-center justify-between gap-3">
                    <FormLabel>{t('tasks.form.recurrence.workdaysOnly')}</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} disabled={disabled} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
            </div>

            <RecurrencePreview form={form} />
          </div>
        ) : null}
      </section>
    </Form>
  )
}
