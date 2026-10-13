import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { ForSelectFormField, ForSelectMultiFormField } from '@/features/commission-settlements/for-select-form-field'
import { useCommissionSettlementRunForm } from '@/features/commission-settlement-runs/use-commission-settlement-run-form'
import { COMMISSION_RECIPIENT_ROLES, type CommissionSettlementRunResult } from '@/features/commission-settlement-runs/types'

interface CommissionSettlementRunFormProps {
  onCreated: (result: CommissionSettlementRunResult) => void
  onCancel: () => void
}

/** "Nuovo lancio" form: period, optional company/agent/role filters and minimum amount. */
export function CommissionSettlementRunForm({ onCreated, onCancel }: CommissionSettlementRunFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useCommissionSettlementRunForm(onCreated)
  const control = form.control

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto p-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={control}
            name="period_from"
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel required className="text-xs">{t('commissionSettlementRuns.form.periodFrom')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
          <FormField
            control={control}
            name="period_to"
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel required className="text-xs">{t('commissionSettlementRuns.form.periodTo')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
        </div>
        <p className="text-xs text-muted-foreground">{t('commissionSettlementRuns.form.periodHint')}</p>

        <ForSelectFormField
          control={control}
          name="company_id"
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          label={t('commissionSettlementRuns.form.company')}
          placeholder={t('commissionSettlementRuns.form.companyPlaceholder')}
        />
        <ForSelectMultiFormField
          control={control}
          name="referent_ids"
          resource={REFERENTS_FOR_SELECT_RESOURCE}
          label={t('commissionSettlementRuns.form.referents')}
          placeholder={t('commissionSettlementRuns.form.referentsPlaceholder')}
        />

        <FormField
          control={control}
          name="recipient_roles"
          render={({ field }) => (
            <FormItem className="gap-1">
              <fieldset className="flex flex-col gap-1.5">
                <legend className="mb-1 text-xs font-medium">{t('commissionSettlementRuns.form.roles')}</legend>
                <div className="flex flex-wrap gap-x-4 gap-y-1.5">
                  {COMMISSION_RECIPIENT_ROLES.map((role) => (
                    <label key={role} className="flex items-center gap-1.5 text-xs">
                      <Checkbox
                        checked={field.value.includes(role)}
                        onCheckedChange={(checked) =>
                          field.onChange(checked ? [...field.value, role] : field.value.filter((r) => r !== role))
                        }
                      />
                      {t(`enums.commission_recipient_role.${role}`)}
                    </label>
                  ))}
                </div>
              </fieldset>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />

        <FormField
          control={control}
          name="min_amount"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="text-xs">{t('commissionSettlementRuns.form.minAmount')}</FormLabel>
              <FormControl>
                <Input type="number" step="0.01" min={0} inputMode="decimal" {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onCancel}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {isPending ? t('commissionSettlementRuns.form.launching') : t('commissionSettlementRuns.form.launch')}
          </Button>
        </div>
      </form>
    </Form>
  )
}
