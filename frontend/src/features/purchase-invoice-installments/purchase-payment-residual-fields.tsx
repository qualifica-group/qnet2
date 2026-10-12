import type { UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { formatEuro } from '@/features/invoices/invoice-format'
import { RESIDUAL_MODES, type ResidualMode } from '@/features/invoices/types'
import type { PurchasePaymentFormValues } from '@/features/purchase-invoice-installments/purchase-installment-payment'

interface ResidualFieldsProps {
  form: UseFormReturn<PurchasePaymentFormValues>
  residualAmount: number
  canSpread: boolean
  residualMode: ResidualMode | undefined
}

/** Choice of what happens to the part not paid: spread on later installments or a new one. */
export function PurchasePaymentResidualFields({ form, residualAmount, canSpread, residualMode }: ResidualFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3">
      <p className="text-xs text-muted-foreground">
        {t('purchaseInvoiceInstallments.payment.residualIntro', { amount: formatEuro(residualAmount) })}
      </p>
      <FormField
        control={form.control}
        name="residual_mode"
        render={({ field }) => (
          <FormItem className="gap-1">
            <fieldset className="flex flex-col gap-1.5">
              <legend className="mb-1 text-xs font-medium">{t('purchaseInvoiceInstallments.payment.residualMode')}</legend>
              {RESIDUAL_MODES.map((mode) => {
                const disabled = mode === 'spread' && !canSpread
                const inputId = `purchase-residual-mode-${mode}`
                return (
                  <div key={mode} className="flex flex-col gap-0.5">
                    <label htmlFor={inputId} className="flex items-center gap-2 text-xs">
                      <input
                        id={inputId}
                        type="radio"
                        name={field.name}
                        value={mode}
                        checked={field.value === mode}
                        disabled={disabled}
                        onChange={() => field.onChange(mode)}
                        onBlur={field.onBlur}
                        className="size-3.5 accent-primary"
                        aria-describedby={disabled ? `${inputId}-reason` : undefined}
                      />
                      {t(`purchaseInvoiceInstallments.payment.residualModes.${mode}`)}
                    </label>
                    {disabled ? (
                      <span id={`${inputId}-reason`} className="pl-5 text-xs text-muted-foreground">
                        {t('purchaseInvoiceInstallments.payment.spreadDisabled')}
                      </span>
                    ) : null}
                  </div>
                )
              })}
            </fieldset>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
      {residualMode === 'new_installment' ? (
        <FormField
          control={form.control}
          name="residual_due_date"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="text-xs">{t('purchaseInvoiceInstallments.payment.residualDueDate')}</FormLabel>
              <FormControl>
                <Input type="date" {...field} value={field.value ?? ''} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      ) : null}
    </div>
  )
}
