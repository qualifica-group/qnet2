import type { UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { InvoiceEditorBankField } from '@/features/invoices/invoice-editor-bank-field'
import { InvoiceLayoutField } from '@/features/invoices/invoice-layout-field'
import { InvoiceTagField } from '@/features/invoices/invoice-tag-field'
import { InvoiceEditorRelationField } from '@/features/invoices/invoice-editor-relation-field'
import type { EditorSource } from '@/features/invoices/invoice-editor-source'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'

interface InvoiceEditorHeaderSectionProps {
  form: UseFormReturn<InvoiceWriteFormValues>
  source: EditorSource
  companyId: number
  /** The issuing company is fixed once the document exists (it owns the numbering). */
  companyLocked: boolean
  /** Collected installments exist: date, customer and payment method cannot change (spec 0196, D-1). */
  collectionsLocked: boolean
}

/** Testata: date, issuer, customer, payment, bank, tag and notes. */
export function InvoiceEditorHeaderSection({ form, source, companyId, companyLocked, collectionsLocked }: InvoiceEditorHeaderSectionProps) {
  const { t } = useTranslation()
  const { control, setValue } = form

  return (
    <section aria-labelledby="invoice-editor-header" className="grid gap-3 rounded-lg border bg-card p-3">
      <h3 id="invoice-editor-header" className="text-sm font-semibold">
        {t('invoiceEditor.sections.header')}
      </h3>
      {collectionsLocked ? (
        <p className="rounded-md border bg-surface px-3 py-2 text-xs text-muted-foreground">
          {t('invoices.rebalance.lockedHint')}
        </p>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <FormField
          control={control}
          name="document_date"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">
                {t('invoiceEditor.fields.documentDate')}
              </FormLabel>
              <FormControl>
                <Input type="date" {...field} disabled={collectionsLocked} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <InvoiceEditorRelationField
          control={control}
          name="company_id"
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          label={t('invoiceEditor.fields.company')}
          placeholder={t('invoiceEditor.fields.selectCompany')}
          selected={source.company}
          required
          disabled={companyLocked}
          onPicked={() => setValue('financial_account_id', null)}
        />
        <InvoiceEditorRelationField
          control={control}
          name="customer_registry_id"
          resource={REGISTRIES_FOR_SELECT_RESOURCE}
          label={t('invoiceEditor.fields.customer')}
          placeholder={t('invoiceEditor.fields.selectCustomer')}
          selected={source.customer}
          required
          disabled={collectionsLocked}
        />
        <InvoiceEditorRelationField
          control={control}
          name="payment_method_id"
          resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
          label={t('invoiceEditor.fields.paymentMethod')}
          placeholder={t('invoiceEditor.fields.selectPaymentMethod')}
          selected={source.paymentMethod}
          required
          disabled={collectionsLocked}
        />
        <InvoiceEditorBankField
          control={control}
          companyId={companyId}
          bankAccounts={source.bankAccounts}
          selected={source.financialAccount}
        />
        <InvoiceLayoutField control={control} selected={source.layout} />
        <InvoiceTagField control={control} selected={source.tag} labelClassName="text-xs" />
      </div>
      <div className="grid gap-3 md:grid-cols-2">
        {(['notes', 'internal_note'] as const).map((name) => (
          <FormField
            key={name}
            control={control}
            name={name}
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel className="text-xs">
                  {t(name === 'notes' ? 'invoiceEditor.fields.notes' : 'invoiceEditor.fields.internalNote')}
                </FormLabel>
                <FormControl>
                  <Textarea
                    rows={2}
                    className="min-h-12 text-xs"
                    {...field}
                    value={field.value ?? ''}
                  />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
        ))}
      </div>
    </section>
  )
}
