import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { Checkbox } from '@/components/ui/checkbox'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { ALLOWANCE_UNITS, type AllowanceFormValues } from '@/features/absences/balances/allowance-schema'

interface AllowanceFormFieldsProps {
  form: UseFormReturn<AllowanceFormValues>
  /** Without a working-day length the days unit makes no sense. */
  canUseDays: boolean
  /** Single-person editing offers the unit switch and the carry-over; the bulk dialog is days only. */
  isBulk?: boolean
}

/** Fields shared by the single and the bulk allowance dialogs: type default switch, unit, amount, carry-over. */
export function AllowanceFormFields({ form, canUseDays, isBulk = false }: AllowanceFormFieldsProps) {
  const { t } = useTranslation()
  const useDefault = form.watch('use_default')
  const unit = form.watch('unit')
  const units = ALLOWANCE_UNITS.filter((value) => canUseDays || value === 'hours')

  return (
    <>
      <FormField
        control={form.control}
        name="use_default"
        render={({ field }) => (
          <FormItem className="flex flex-row items-center gap-2">
            <FormControl>
              <Checkbox checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />
            </FormControl>
            <FormLabel className="font-normal">{t('absences.balances.form.useDefault')}</FormLabel>
          </FormItem>
        )}
      />
      {isBulk ? null : (
        <FormField
          control={form.control}
          name="unit"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('absences.balances.form.unit')}</FormLabel>
              <FormControl>
                <SegmentedControl
                  value={field.value}
                  disabled={useDefault}
                  options={units.map((value) => ({ value, label: t(`absences.balances.form.units.${value}`) }))}
                  onValueChange={field.onChange}
                />
              </FormControl>
            </FormItem>
          )}
        />
      )}
      <FormField
        control={form.control}
        name="annual_value"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t(`absences.balances.form.annual_${unit}`)}</FormLabel>
            <FormControl>
              <Input {...field} disabled={useDefault} inputMode="decimal" autoComplete="off" />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      {isBulk ? null : (
        <FormField
          control={form.control}
          name="carried_hours"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('absences.balances.form.carried')}</FormLabel>
              <FormControl>
                <Input {...field} inputMode="decimal" autoComplete="off" />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      )}
    </>
  )
}
