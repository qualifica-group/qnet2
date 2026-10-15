import { useTranslation } from 'react-i18next'
import { FileText, NotebookText } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordRelationEditor, RecordTextEditor } from '@/components/record-form/record-field-editors'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { BANK_ACCOUNT_FOR_SELECT_PARAMS, FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import type { PurchaseInvoiceRecordEditor } from '@/features/purchase-invoices/use-purchase-invoice-inline-edit'

interface SectionProps {
  invoice: PurchaseInvoice
  editor: PurchaseInvoiceRecordEditor
}

function TextValue({ value }: { value: string | null | undefined }) {
  return value && value.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{value}</span> : <DetailEmpty />
}

/**
 * "Testata": the proforma header. Supplier and company are fixed once the
 * document exists; payments freeze date and payment method (spec 0250); a
 * registered invoice locks the whole header through the server's field matrix.
 */
function HeaderSection({ invoice, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form
  const unfrozen = !invoice.has_payments
  const unlocked = invoice.commission_settlement == null
  const field = (key: string) => t(`purchaseInvoiceViews.detail.fields.${key}`)
  const pick = (key: string) => t(`purchaseInvoices.editor.fields.${key}`)

  return (
    <RecordSection title={t('purchaseInvoiceViews.detail.header')} icon={<FileText />}>
      <RecordFieldList>
        <RecordInlineField
          field="document_date"
          label={field('document_date')}
          inline={inline}
          canEdit={unfrozen && unlocked}
          editor={<RecordTextEditor control={control} name="document_date" type="date" label={field('document_date')} required />}
        >
          <span>{formatDate(invoice.document_date)}</span>
        </RecordInlineField>
        <RecordField label={field('company')}>
          <span>{invoice.company.name}</span>
        </RecordField>
        <RecordField label={field('supplier')}>
          <span>{invoice.supplier.name}</span>
        </RecordField>
        <RecordInlineField
          field="payment_method_id"
          label={field('payment_method')}
          inline={inline}
          canEdit={unfrozen && unlocked}
          editor={<RecordRelationEditor control={control} name="payment_method_id" label={field('payment_method')} resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE} selected={invoice.payment_method} placeholder={pick('selectPaymentMethod')} required />}
        >
          <span>{invoice.payment_method.name}</span>
        </RecordInlineField>
        <RecordInlineField
          field="financial_account_id"
          canEdit={unlocked}
          label={field('financial_account')}
          inline={inline}
          editor={
            <RecordRelationEditor
              control={control}
              name="financial_account_id"
              label={field('financial_account')}
              resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
              selected={invoice.financial_account}
              params={{ ...BANK_ACCOUNT_FOR_SELECT_PARAMS, company_id: invoice.company.id }}
              placeholder={pick('noBank')}
            />
          }
        >
          <TextValue value={invoice.financial_account?.name} />
        </RecordInlineField>
        <RecordInlineField
          field="company_site_id"
          canEdit={unlocked}
          label={field('company_site')}
          inline={inline}
          editor={<RecordRelationEditor control={control} name="company_site_id" label={field('company_site')} resource={COMPANY_SITES_FOR_SELECT_RESOURCE} selected={invoice.company_site} params={{ company_id: invoice.company.id }} placeholder={pick('selectCompanySite')} />}
        >
          <TextValue value={invoice.company_site?.name} />
        </RecordInlineField>
        <RecordInlineField
          field="operational_site_id"
          canEdit={unlocked}
          label={field('operational_site')}
          inline={inline}
          editor={<RecordRelationEditor control={control} name="operational_site_id" label={field('operational_site')} resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE} selected={invoice.operational_site} placeholder={pick('selectOperationalSite')} />}
        >
          <TextValue value={invoice.operational_site?.name} />
        </RecordInlineField>
        <RecordInlineField
          field="cost_type_id"
          canEdit={unlocked}
          label={field('cost_type')}
          inline={inline}
          editor={<RecordRelationEditor control={control} name="cost_type_id" label={field('cost_type')} resource={COST_TYPES_FOR_SELECT_RESOURCE} selected={invoice.cost_type} placeholder={pick('selectCostType')} />}
        >
          {invoice.cost_type ? <CostTypeBadge name={invoice.cost_type.name} color={invoice.cost_type.color} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="work_order_id"
          canEdit={unlocked}
          label={field('work_order')}
          inline={inline}
          editor={
            <RecordRelationEditor
              control={control}
              name="work_order_id"
              label={field('work_order')}
              resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
              selected={invoice.work_order ? { id: invoice.work_order.id, name: invoice.work_order.code } : null}
              placeholder={pick('selectWorkOrder')}
            />
          }
        >
          <TextValue value={invoice.work_order?.code} />
        </RecordInlineField>
        <RecordField label={field('created_by')}>
          <span>{invoice.created_by.name}</span>
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Registrazione e note": the supplier number (a proforma becomes an invoice) and the notes, editable after registration too. */
function RegistrationSection({ invoice, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const numberLabel = t('purchaseInvoices.details.documentNumber')
  const notesLabel = t('purchaseInvoiceViews.detail.notes')

  return (
    <RecordSection title={t('purchaseInvoiceViews.record.registration')} icon={<NotebookText />} full>
      <RecordFieldList>
        <RecordInlineField
          field="document_number"
          canEdit={invoice.commission_settlement == null}
          label={numberLabel}
          inline={inline}
          editor={
            <div className="flex min-w-0 flex-col gap-2">
              <RecordTextEditor control={form.control} name="document_number" label={numberLabel} />
              <p className="text-xs text-muted-foreground">
                {t(invoice.purchase_orders.length > 0 && invoice.document_type === 'proforma' ? 'purchaseInvoices.details.receiptNotice' : 'purchaseInvoices.details.numberHint')}
              </p>
            </div>
          }
        >
          <TextValue value={invoice.document_number} />
        </RecordInlineField>
        <RecordInlineField
          field="notes"
          label={notesLabel}
          inline={inline}
          editor={<RecordTextEditor control={form.control} name="notes" label={notesLabel} multiline />}
        >
          <TextValue value={invoice.notes} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The passive invoice record's editable sections (spec 0262 D-4). */
export function PurchaseInvoiceRecordSections(props: SectionProps) {
  return (
    <>
      <HeaderSection {...props} />
      <RegistrationSection {...props} />
    </>
  )
}
