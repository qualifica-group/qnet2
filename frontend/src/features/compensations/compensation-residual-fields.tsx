import type { UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { CompensationFormValues } from '@/features/compensations/compensation-math'
import { COMPENSATION_RESIDUAL_MODES, type CompensationResidualMode } from '@/features/compensations/types'

interface ResidualFieldsProps {
  form: UseFormReturn<CompensationFormValues>
  residualMode: CompensationResidualMode | undefined
}

/** What happens to the larger installment's remainder: spread on later ones or a new installment (no write-off). */
export function CompensationResidualFields({ form, residualMode }: ResidualFieldsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-surface p-3">
      <p className="text-xs text-muted-foreground">{t('compensations.dialog.residualIntro')}</p>
      <FormField
        control={form.control}
        name="residual_mode"
        render={({ field }) => (
          <FormItem className="gap-1">
            <fieldset className="flex flex-col gap-1.5">
              <legend className="mb-1 text-xs font-medium">{t('compensations.dialog.residualMode')}</legend>
              {COMPENSATION_RESIDUAL_MODES.map((mode) => {
                const inputId = `compensation-residual-${mode}`
                return (
                  <label key={mode} htmlFor={inputId} className="flex items-center gap-2 text-xs">
                    <input
                      id={inputId}
                      type="radio"
                      name={field.name}
                      value={mode}
                      checked={residualMode === mode}
                      onChange={() => field.onChange(mode)}
                      onBlur={field.onBlur}
                      className="size-3.5 accent-primary"
                    />
                    {t(`compensations.dialog.residualModes.${mode}`)}
                  </label>
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
              <FormLabel className="text-xs">{t('compensations.dialog.residualDueDate')}</FormLabel>
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
