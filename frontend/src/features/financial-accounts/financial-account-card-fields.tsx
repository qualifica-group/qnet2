import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { FinancialAccountSelectField } from '@/features/financial-accounts/financial-account-select-field'
import { FinancialAccountTextField } from '@/features/financial-accounts/financial-account-text-field'
import { CompanyField, NotesField } from '@/features/financial-accounts/financial-account-shared-fields'
import {
  BANK_ACCOUNT_FOR_SELECT_PARAMS,
  FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE,
} from '@/features/financial-accounts/for-select-api'
import { CARD_CIRCUITS, CARD_TYPES, type FinancialAccountDetail } from '@/features/financial-accounts/types'
import type { FinancialAccountFormValues } from '@/features/financial-accounts/financial-account-schema'

interface CardFieldsProps {
  form: UseFormReturn<FinancialAccountFormValues>
  account: FinancialAccountDetail | null
}

/**
 * Carta: Tipo carta, Banca, Circuito, Associa al conto, Intestatario, Numero
 * carta, Scadenza, Societa', Note. There is deliberately no CVV/PIN field: the
 * backend never stores them (spec 0189 D-1). The card number is required on
 * create; on edit the mask is shown and a typed value replaces it.
 */
export function CardFields({ form, account }: CardFieldsProps) {
  const { t } = useTranslation()
  const isEdit = account !== null
  const cardType = useWatch({ control: form.control, name: 'card_type' })

  const cardTypeOptions = CARD_TYPES.map((value) => ({
    value,
    label: t(`financialAccounts.cardTypes.${value}`),
  }))
  const circuitOptions = CARD_CIRCUITS.map((value) => ({
    value,
    label: t(`financialAccounts.circuits.${value}`),
  }))

  const linkedAccount = account?.linked_account
    ? { id: account.linked_account.id, name: account.linked_account.name }
    : null

  return (
    <>
      <FinancialAccountSelectField
        control={form.control}
        name="card_type"
        label={t('financialAccounts.form.cardType')}
        options={cardTypeOptions}
        placeholder={t('financialAccounts.form.cardTypePlaceholder')}
        required
        onValueChange={() => form.clearErrors('linked_account_id')}
      />
      <FinancialAccountTextField
        control={form.control}
        name="name"
        label={t('financialAccounts.form.bank')}
        required
      />
      <FinancialAccountSelectField
        control={form.control}
        name="card_circuit"
        label={t('financialAccounts.form.cardCircuit')}
        options={circuitOptions}
        placeholder={t('financialAccounts.form.cardCircuitPlaceholder')}
        required
      />
      <RelationSelectField
        control={form.control}
        name="linked_account_id"
        metaKey="linked_account_id"
        label={t('financialAccounts.form.linkedAccount')}
        resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
        params={BANK_ACCOUNT_FOR_SELECT_PARAMS}
        searchPlaceholder={t('financialAccounts.form.linkedAccountSearch')}
        selected={linkedAccount}
        required={cardType === 'credit'}
        placeholder={t('financialAccounts.form.linkedAccountPlaceholder')}
        emptyLabel={t('financialAccounts.form.linkedAccountEmpty')}
        errorLabel={t('financialAccounts.form.linkedAccountError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />
      <FinancialAccountTextField
        control={form.control}
        name="card_holder"
        label={t('financialAccounts.form.cardHolder')}
        autoComplete="off"
        required
      />
      <FinancialAccountTextField
        control={form.control}
        name="card_number"
        label={t('financialAccounts.form.cardNumber')}
        required={!isEdit}
        inputMode="numeric"
        placeholder={account?.card_number_masked ?? undefined}
        hint={isEdit ? t('financialAccounts.form.cardNumberKeepHint') : undefined}
      />
      <FinancialAccountTextField
        control={form.control}
        name="card_expiry"
        label={t('financialAccounts.form.cardExpiry')}
        placeholder={t('financialAccounts.form.cardExpiryPlaceholder')}
        required
      />
      <CompanyField form={form} selected={account?.company ?? null} />
      <NotesField form={form} />
    </>
  )
}
