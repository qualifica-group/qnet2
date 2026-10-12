import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { PurchaseInstallmentEditFormValues } from '@/features/purchase-invoice-installments/purchase-installment-schema'
import { usePaymentMethodCodeOptions } from '@/features/purchase-invoice-installments/use-payment-method-code-options'

/** Radix Select cannot hold an empty value: this sentinel stands for "no method code". */
const NO_METHOD = '__none__'

interface PurchasePaymentMethodFieldProps {
  control: Control<PurchaseInstallmentEditFormValues>
  readOnly: boolean
}

/** Method code as a select of the existing payment methods; a stored code outside the list stays selectable. */
export function PurchasePaymentMethodField({ control, readOnly }: PurchasePaymentMethodFieldProps) {
  const { t } = useTranslation()
  const { data: options = [] } = usePaymentMethodCodeOptions(!readOnly)

  return (
    <FormField
      control={control}
      name="payment_method_code"
      render={({ field }) => {
        const known = options.some((option) => option.code === field.value)
        return (
          <FormItem>
            <FormLabel>{t('purchaseInvoiceInstallments.edit.paymentMethodCode')}</FormLabel>
            <Select
              value={field.value === '' ? NO_METHOD : field.value}
              onValueChange={(value) => field.onChange(value === NO_METHOD ? '' : value)}
              disabled={readOnly}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('purchaseInvoiceInstallments.edit.paymentMethodPlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value={NO_METHOD}>{t('purchaseInvoiceInstallments.edit.paymentMethodNone')}</SelectItem>
                {field.value !== '' && !known ? <SelectItem value={field.value}>{field.value}</SelectItem> : null}
                {options.map((option) => (
                  <SelectItem key={option.code} value={option.code}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FormDescription className="text-xs">{t('purchaseInvoiceInstallments.edit.paymentMethodHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )
      }}
    />
  )
}
