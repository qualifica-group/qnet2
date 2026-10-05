import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { FinancialAccountTextField } from '@/features/financial-accounts/financial-account-text-field'
import {
  AddressFields,
  CompanyField,
  NotesField,
} from '@/features/financial-accounts/financial-account-shared-fields'
import type { FinancialAccountFormValues } from '@/features/financial-accounts/financial-account-schema'
import type { FinancialAccountDetail } from '@/features/financial-accounts/types'

interface CashFieldsProps {
  form: UseFormReturn<FinancialAccountFormValues>
  account: FinancialAccountDetail | null
}

/** Cassa: Nome, Societa', address, Note. */
export function CashFields({ form, account }: CashFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      <FinancialAccountTextField
        control={form.control}
        name="name"
        label={t('financialAccounts.form.cashName')}
        required
      />
      <CompanyField form={form} selected={account?.company ?? null} />
      <AddressFields form={form} />
      <NotesField form={form} />
    </>
  )
}
