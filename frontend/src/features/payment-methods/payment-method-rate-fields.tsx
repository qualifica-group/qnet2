import { CalendarClock } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { VAT_ALLOCATIONS } from '@/features/payment-methods/types'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'

interface PaymentMethodRateFieldsProps {
  control: Control<PaymentMethodFormValues>
}

type NumericRateField =
  | 'installments_count'
  | 'days_between_installments'
  | 'end_of_month_extra_days'

interface NumericRateInputProps extends PaymentMethodRateFieldsProps {
  name: NumericRateField
  label: string
  min: number
  max: number
  hint?: string
}

/** Metadata-aware integer input; an empty box maps to `null`. */
function NumericRateInput({ control, name, label, min, max, hint }: NumericRateInputProps) {
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
            onChange={(event) =>
              field.onChange(event.target.value === '' ? null : Number(event.target.value))
            }
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
 * Installment configuration of a payment method (spec 0194 D-10): count, gap
 * between installments, end-of-month rule and VAT allocation. The first due
 * date offset is the existing `payment_days` field.
 */
export function PaymentMethodRateFields({ control }: PaymentMethodRateFieldsProps) {
  const { t } = useTranslation()
  const endOfMonth = useWatch({ control, name: 'end_of_month' })

  return (
    <FormSection
      icon={CalendarClock}
      title={t('paymentMethods.form.sections.installments.title')}
      description={t('paymentMethods.form.sections.installments.description')}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <NumericRateInput
          control={control}
          name="installments_count"
          label={t('paymentMethods.form.installmentsCount')}
          min={1}
          max={60}
        />
        <NumericRateInput
          control={control}
          name="days_between_installments"
          label={t('paymentMethods.form.daysBetweenInstallments')}
          min={0}
          max={365}
          hint={t('paymentMethods.form.hints.daysBetweenInstallments')}
        />
      </div>

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
          control={control}
          name="end_of_month_extra_days"
          label={t('paymentMethods.form.endOfMonthExtraDays')}
          min={0}
          max={31}
          hint={t('paymentMethods.form.hints.endOfMonthExtraDays')}
        />
      ) : null}

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
    </FormSection>
  )
}
