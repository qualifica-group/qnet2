import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { PAYMENT_CONDITIONS } from '@/features/payment-methods/types'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'
import { monthName } from '@/features/tasks/task-recurrence-format'

interface DueDateRowProps {
  inline: InlineEdit
  canEdit: boolean
}

type ExcludedMonthField = 'excluded_month_1' | 'excluded_month_2'

/** Radix Select cannot hold an empty value: this option stands for "no month". */
const NO_MONTH = 'none'

const MONTHS = Array.from({ length: 12 }, (_, index) => index + 1)

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

/** The FatturaPA payment condition (spec 0325 D-1). */
export function PaymentConditionRow({ inline, canEdit }: DueDateRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()
  const condition = useWatch({ control, name: 'payment_condition' })

  return (
    <RecordInlineField
      field="payment_condition"
      label={t('paymentMethods.form.paymentCondition')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField
          control={control}
          name="payment_condition"
          metaKey="payment_condition"
          label={t('paymentMethods.form.paymentCondition')}
          hint={t('paymentMethods.form.hints.paymentCondition')}
        >
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {PAYMENT_CONDITIONS.map((value) => (
                  <SelectItem key={value} value={value}>
                    {t(`paymentMethods.paymentConditions.${value}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {t(`paymentMethods.paymentConditions.${condition}`)}
    </RecordInlineField>
  )
}

function ExcludedMonthSelect({ name, label }: { name: ExcludedMonthField; label: string }) {
  const { t, i18n } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()

  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled }) => (
        <Select
          value={field.value === null ? NO_MONTH : String(field.value)}
          onValueChange={(value) => field.onChange(value === NO_MONTH ? null : Number(value))}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            <SelectItem value={NO_MONTH}>{t('paymentMethods.detail.noExcludedMonth')}</SelectItem>
            {MONTHS.map((month) => (
              <SelectItem key={month} value={String(month)}>
                {capitalize(monthName(month, i18n.language))}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

/** The two excluded months open, save and cancel as ONE row (spec 0325 D-2). */
export function ExcludedMonthsRow({ inline, canEdit }: DueDateRowProps) {
  const { t, i18n } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()
  const months = useWatch({ control, name: ['excluded_month_1', 'excluded_month_2'] })
  const names = months.filter((month): month is number => month !== null).map((month) => capitalize(monthName(month, i18n.language)))

  return (
    <RecordInlineField
      field="excluded_month_1"
      label={t('paymentMethods.form.excludedMonths')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <div className="flex flex-col gap-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <ExcludedMonthSelect name="excluded_month_1" label={t('paymentMethods.form.excludedMonth1')} />
            <ExcludedMonthSelect name="excluded_month_2" label={t('paymentMethods.form.excludedMonth2')} />
          </div>
          <p className="text-xs text-muted-foreground">{t('paymentMethods.form.hints.excludedMonths')}</p>
        </div>
      }
    >
      {names.length > 0 ? names.join(', ') : t('paymentMethods.detail.noExcludedMonth')}
    </RecordInlineField>
  )
}
