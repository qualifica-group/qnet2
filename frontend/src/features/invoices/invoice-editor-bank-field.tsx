import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { InvoiceEditorRelationField } from '@/features/invoices/invoice-editor-relation-field'
import type { EditorBankAccount } from '@/features/invoices/invoice-editor-source'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import type { BankAccountRef } from '@/features/invoices/types'
import { BANK_ACCOUNT_FOR_SELECT_PARAMS, FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'

const NO_BANK = 'none'

interface InvoiceEditorBankFieldProps {
  control: Control<InvoiceWriteFormValues>
  companyId: number
  /** Draft candidates (create); null in edit, where the bank list comes from the financial-accounts for-select. */
  bankAccounts: EditorBankAccount[] | null
  selected: BankAccountRef | null
}

/** Bank of the issuing company: draft candidates in create, for-select in edit. */
export function InvoiceEditorBankField({ control, companyId, bankAccounts, selected }: InvoiceEditorBankFieldProps) {
  const { t } = useTranslation()
  const label = t('invoiceEditor.fields.financialAccount')

  if (bankAccounts === null) {
    return (
      <InvoiceEditorRelationField
        control={control}
        name="financial_account_id"
        resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
        label={label}
        placeholder={t('invoiceEditor.fields.noBank')}
        selected={selected}
        params={{ ...BANK_ACCOUNT_FOR_SELECT_PARAMS, company_id: companyId }}
      />
    )
  }

  const options = bankAccounts.filter((bank) => bank.company_id === companyId)

  return (
    <FormField
      control={control}
      name="financial_account_id"
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel className="text-xs">{label}</FormLabel>
          <Select
            value={field.value === null ? NO_BANK : String(field.value)}
            onValueChange={(value) => field.onChange(value === NO_BANK ? null : Number(value))}
          >
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value={NO_BANK}>{t('invoiceEditor.fields.noBank')}</SelectItem>
              {options.map((bank) => (
                <SelectItem key={bank.id} value={String(bank.id)}>
                  {bank.iban ? `${bank.name} - ${bank.iban}` : bank.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
