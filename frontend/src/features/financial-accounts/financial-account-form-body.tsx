import { Landmark } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { BankAccountFields } from '@/features/financial-accounts/financial-account-bank-fields'
import { CardFields } from '@/features/financial-accounts/financial-account-card-fields'
import { CashFields } from '@/features/financial-accounts/financial-account-cash-fields'
import { FinancialAccountSelectField } from '@/features/financial-accounts/financial-account-select-field'
import { useFinancialAccountForm } from '@/features/financial-accounts/use-financial-account-form'
import {
  FINANCIAL_ACCOUNT_TYPES,
  type FinancialAccountDetail,
  type FinancialAccountFormMode,
} from '@/features/financial-accounts/types'

interface FinancialAccountFormBodyProps {
  mode: FinancialAccountFormMode
  onSuccess: (account: FinancialAccountDetail) => void
  onCancel: () => void
}

/**
 * The financial-account create/edit form UI. The user first picks the type
 * (read-only on edit: changing it would leave incoherent fields), then only
 * that type's fields are rendered. All non-render logic lives in
 * `useFinancialAccountForm`.
 */
export function FinancialAccountFormBody({ mode, onSuccess, onCancel }: FinancialAccountFormBodyProps) {
  const { t } = useTranslation()
  const { form, isEdit, serverError, onSubmit } = useFinancialAccountForm({ mode, onSuccess })
  const type = useWatch({ control: form.control, name: 'type' })
  const account = mode.type === 'edit' ? mode.financialAccount : null

  const typeOptions = FINANCIAL_ACCOUNT_TYPES.map((value) => ({
    value,
    label: t(`financialAccounts.types.${value}`),
  }))

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          <FormSection
            icon={Landmark}
            title={t('financialAccounts.form.sections.details.title')}
            description={t('financialAccounts.form.sections.details.description')}
          >
            <FinancialAccountSelectField
              control={form.control}
              name="type"
              label={t('financialAccounts.form.type')}
              options={typeOptions}
              forceDisabled={isEdit}
              required
              onValueChange={() => form.clearErrors()}
            />

            {type === 'bank_account' && <BankAccountFields form={form} account={account} />}
            {type === 'card' && <CardFields form={form} account={account} />}
            {type === 'cash' && <CashFields form={form} account={account} />}
          </FormSection>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('financialAccounts.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('financialAccounts.form.saving')
                : t('financialAccounts.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
