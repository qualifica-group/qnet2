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

interface BankAccountFieldsProps {
  form: UseFormReturn<FinancialAccountFormValues>
  account: FinancialAccountDetail | null
}

/** Conto Corrente: Banca, IBAN, Conto Corrente, Societa', address, Note. */
export function BankAccountFields({ form, account }: BankAccountFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      <FinancialAccountTextField
        control={form.control}
        name="name"
        label={t('financialAccounts.form.bank')}
        required
      />
      <FinancialAccountTextField
        control={form.control}
        name="iban"
        label={t('financialAccounts.form.iban')}
        required
      />
      <FinancialAccountTextField
        control={form.control}
        name="account_number"
        label={t('financialAccounts.form.accountNumber')}
        required
      />
      <CompanyField form={form} selected={account?.company ?? null} />
      <AddressFields form={form} />
      <NotesField form={form} />
    </>
  )
}
