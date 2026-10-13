import type { UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import {
  BANK_ACCOUNT_FOR_SELECT_PARAMS,
  FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE,
} from '@/features/financial-accounts/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { PurchaseInvoiceEditorRelationField } from '@/features/purchase-invoices/purchase-invoice-editor-relation-field'
import type { EditorSource } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'
import { PURCHASE_INVOICE_TYPES } from '@/features/purchase-invoices/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'

/** Supplier picker filter: only registries flagged `is_supplier`. */
const SUPPLIER_PARAMS = { is_supplier: 1 } as const

interface PurchaseInvoiceEditorHeaderSectionProps {
  form: UseFormReturn<PurchaseInvoiceWriteFormValues>
  source: EditorSource
  companyId: number
  documentType: PurchaseInvoiceWriteFormValues['document_type']
  isEdit: boolean
  /** Supplier and company are fixed once the document exists or ODAs are linked. */
  partiesLocked: boolean
}

/** Testata: type, supplier number, date, parties, payment, bank, sites, default cost type/work order and notes. */
export function PurchaseInvoiceEditorHeaderSection({
  form,
  source,
  companyId,
  documentType,
  isEdit,
  partiesLocked,
}: PurchaseInvoiceEditorHeaderSectionProps) {
  const { t } = useTranslation()
  const { control, setValue } = form
  const label = (key: string) => t(`purchaseInvoices.editor.fields.${key}`)
  const paymentsLocked = source.hasPayments
  const companyParams = Number.isNaN(companyId) ? undefined : { company_id: companyId }

  return (
    <section aria-labelledby="purchase-invoice-editor-header" className="grid gap-3 rounded-lg border bg-card p-3">
      <h3 id="purchase-invoice-editor-header" className="text-sm font-semibold">
        {t('purchaseInvoices.editor.sections.header')}
      </h3>
      {paymentsLocked ? (
        <p className="rounded-md border bg-surface px-3 py-2 text-xs text-muted-foreground">
          {t('purchaseInvoices.editor.paymentsLockedHint')}
        </p>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <FormField
          control={control}
          name="document_type"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">
                {label('documentType')}
              </FormLabel>
              <Select value={field.value} onValueChange={field.onChange} disabled={isEdit}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {PURCHASE_INVOICE_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {t(`purchaseInvoices.types.${type}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        {!isEdit && documentType === 'invoice' ? (
          <FormField
            control={control}
            name="document_number"
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel required className="text-xs">
                  {label('documentNumber')}
                </FormLabel>
                <FormControl>
                  <Input {...field} value={field.value ?? ''} onChange={(event) => field.onChange(event.target.value)} />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
        ) : null}
        <FormField
          control={control}
          name="document_date"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">
                {label('documentDate')}
              </FormLabel>
              <FormControl>
                <Input type="date" {...field} disabled={paymentsLocked} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="supplier_id"
          resource={REGISTRIES_FOR_SELECT_RESOURCE}
          label={label('supplier')}
          placeholder={label('selectSupplier')}
          selected={source.supplier}
          params={SUPPLIER_PARAMS}
          required
          disabled={partiesLocked || paymentsLocked}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="company_id"
          resource={COMPANIES_FOR_SELECT_RESOURCE}
          label={label('company')}
          placeholder={label('selectCompany')}
          selected={source.company}
          required
          disabled={partiesLocked}
          onPicked={() => {
            setValue('financial_account_id', null)
            setValue('company_site_id', null)
          }}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="payment_method_id"
          resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
          label={label('paymentMethod')}
          placeholder={label('selectPaymentMethod')}
          selected={source.paymentMethod}
          required
          disabled={paymentsLocked}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="financial_account_id"
          resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
          label={label('financialAccount')}
          placeholder={label('noBank')}
          selected={source.financialAccount}
          params={{ ...BANK_ACCOUNT_FOR_SELECT_PARAMS, ...companyParams }}
          disabled={companyParams === undefined}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="company_site_id"
          resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
          label={label('companySite')}
          placeholder={label('selectCompanySite')}
          selected={source.companySite}
          params={companyParams}
          disabled={companyParams === undefined}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="operational_site_id"
          resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
          label={label('operationalSite')}
          placeholder={label('selectOperationalSite')}
          selected={source.operationalSite}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="cost_type_id"
          resource={COST_TYPES_FOR_SELECT_RESOURCE}
          label={label('defaultCostType')}
          placeholder={label('selectCostType')}
          selected={source.costType}
          renderItem={costTypeOptionRenderer}
        />
        <PurchaseInvoiceEditorRelationField
          control={control}
          name="work_order_id"
          resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
          label={label('defaultWorkOrder')}
          placeholder={label('selectWorkOrder')}
          selected={source.workOrder}
        />
      </div>
      <p className="-mt-1 text-xs text-muted-foreground">{t('purchaseInvoices.editor.defaultsHint')}</p>
      <FormField
        control={control}
        name="notes"
        render={({ field }) => (
          <FormItem className="gap-1">
            <FormLabel className="text-xs">{label('notes')}</FormLabel>
            <FormControl>
              <Textarea rows={2} className="min-h-12 text-xs" {...field} value={field.value ?? ''} />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
    </section>
  )
}
