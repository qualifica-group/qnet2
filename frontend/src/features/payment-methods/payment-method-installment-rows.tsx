import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { VAT_ALLOCATIONS } from '@/features/payment-methods/types'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'

interface PaymentMethodInstallmentRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

type NumericRateField = 'days_between_installments' | 'installments_count' | 'end_of_month_extra_days'

interface NumericRateInputProps {
  name: NumericRateField
  label: string
  min: number
  max: number
  hint?: string
}

/** Metadata-aware integer input; an empty box maps to `null`. */
function NumericRateInput({ name, label, min, max, hint }: NumericRateInputProps) {
  const { control } = useFormContext<PaymentMethodFormValues>()

  return (
    <MetaField control={control} name={name} metaKey={name} label={label} hint={hint}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            step="1"
            min={min}
            max={max}
            disabled={disabled}
            readOnly={readOnly}
            value={field.value === null ? '' : String(field.value)}
            onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * The message of a field the closed row does not render itself (the second
 * field of a grouped row): a rule refused on it must still show on the row.
 */
function SecondaryFieldError({ name }: { name: NumericRateField }) {
  const { formState } = useFormContext<PaymentMethodFormValues>()
  const message = formState.errors[name]?.message

  return message ? (
    <p role="alert" className="mt-1 text-xs font-medium text-destructive">
      {message}
    </p>
  ) : null
}

/** Count and gap open, save and cancel as ONE row: the gap is mandatory as soon as there is more than one installment. */
function InstallmentScheduleRow({ inline, canEdit }: PaymentMethodInstallmentRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()
  const [count, days] = useWatch({ control, name: ['installments_count', 'days_between_installments'] })

  return (
    <RecordInlineField
      field="installments_count"
      label={t('paymentMethods.form.installmentsCount')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <div className="grid gap-3 sm:grid-cols-2">
          <NumericRateInput
            name="installments_count"
            label={t('paymentMethods.form.installmentsCount')}
            min={1}
            max={60}
          />
          <NumericRateInput
            name="days_between_installments"
            label={t('paymentMethods.form.daysBetweenInstallments')}
            min={0}
            max={365}
            hint={t('paymentMethods.form.hints.daysBetweenInstallments')}
          />
        </div>
      }
    >
      {count > 1
        ? t('paymentMethods.detail.installmentSchedule', { count, days: days ?? 0 })
        : t('paymentMethods.detail.singleInstallment')}
      <SecondaryFieldError name="days_between_installments" />
    </RecordInlineField>
  )
}

/** The end-of-month switch and its extra days (only meaningful while the switch is on) are ONE row. */
function EndOfMonthRow({ inline, canEdit }: PaymentMethodInstallmentRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()
  const [endOfMonth, extraDays] = useWatch({ control, name: ['end_of_month', 'end_of_month_extra_days'] })
  const closedLabel = !endOfMonth
    ? t('common.no')
    : extraDays === null
      ? t('common.yes')
      : t('paymentMethods.detail.endOfMonthWithExtra', { days: extraDays })

  return (
    <RecordInlineField
      field="end_of_month"
      label={t('paymentMethods.form.endOfMonth')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <div className="flex flex-col gap-3">
          <MetaField
            control={control}
            name="end_of_month"
            metaKey="end_of_month"
            label={t('paymentMethods.form.endOfMonth')}
            hint={t('paymentMethods.form.hints.endOfMonth')}
          >
            {({ field, disabled }) => (
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
              </FormControl>
            )}
          </MetaField>
          {endOfMonth ? (
            <NumericRateInput
              name="end_of_month_extra_days"
              label={t('paymentMethods.form.endOfMonthExtraDays')}
              min={0}
              max={31}
              hint={t('paymentMethods.form.hints.endOfMonthExtraDays')}
            />
          ) : null}
        </div>
      }
    >
      {closedLabel}
      <SecondaryFieldError name="end_of_month_extra_days" />
    </RecordInlineField>
  )
}

function VatAllocationRow({ inline, canEdit }: PaymentMethodInstallmentRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<PaymentMethodFormValues>()
  const allocation = useWatch({ control, name: 'vat_allocation' })

  return (
    <RecordInlineField
      field="vat_allocation"
      label={t('paymentMethods.form.vatAllocation')}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField
          control={control}
          name="vat_allocation"
          metaKey="vat_allocation"
          label={t('paymentMethods.form.vatAllocation')}
          hint={t('paymentMethods.form.hints.vatAllocation')}
        >
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {VAT_ALLOCATIONS.map((value) => (
                  <SelectItem key={value} value={value}>
                    {t(`paymentMethods.vatAllocations.${value}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {t(`paymentMethods.vatAllocations.${allocation}`)}
    </RecordInlineField>
  )
}

/** The "Installments" section's rows (spec 0194 D-10), shared by the detail and the create draft. */
export function PaymentMethodInstallmentRows(props: PaymentMethodInstallmentRowsProps) {
  return (
    <>
      <InstallmentScheduleRow {...props} />
      <EndOfMonthRow {...props} />
      <VatAllocationRow {...props} />
    </>
  )
}
