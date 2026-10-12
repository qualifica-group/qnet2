import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FileText, StickyNote } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import { InvoiceEditorBankField } from '@/features/invoices/invoice-editor-bank-field'
import { InvoiceEditorRelationField } from '@/features/invoices/invoice-editor-relation-field'
import type { EditorBankAccount } from '@/features/invoices/invoice-editor-source'
import { InvoiceDocumentDateField, InvoiceNoteField, InvoiceTagField } from '@/features/invoices/invoice-fields'
import { InvoiceLayoutField } from '@/features/invoices/invoice-layout-field'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'
import { InvoiceTagBadge } from '@/features/invoices/invoice-status-badges'
import type { BankAccountRef, InvoiceTag, NamedRef } from '@/features/invoices/types'

/** What the closed rows show: the persisted document on the detail, the draft (ids named) on issue. */
export interface InvoiceRecordDisplay {
  documentDate: string
  company: NamedRef | null
  customer: NamedRef | null
  paymentMethod: NamedRef | null
  financialAccount: BankAccountRef | null
  layout: NamedRef | null
  tag: InvoiceTag | null
  notes: string | null
  internalNote: string | null
}

interface InvoiceRecordSectionsProps {
  display: InvoiceRecordDisplay
  control: Control<InvoiceFormValues>
  inline: InlineEdit
  /** Issue only: the company owns the numbering, so it is fixed once the document exists. */
  companyEditable: boolean
  /** Collected installments: date, customer and payment cannot change (spec 0196 D-1). */
  collectionsLocked: boolean
  /** Issuing company of the bank picker. */
  companyId: number
  /** Draft candidates (issue); null on the detail (for-select by company). */
  bankAccounts: EditorBankAccount[] | null
  /** Picking another company clears the bank (it must belong to the issuer). */
  onCompanyPicked?: () => void
  /** Shown under the date and payment editors: the due dates the save would produce (detail). */
  schedulePreview?: ReactNode
}

function refName(ref: NamedRef | null): ReactNode {
  return ref ? ref.name : <DetailEmpty />
}

function textValue(value: string | null): ReactNode {
  return value ? <span className="whitespace-pre-wrap">{value}</span> : <DetailEmpty />
}

/**
 * Testata and Note of an invoice as record rows (spec 0224): the same rows on
 * the detail (each saved alone) and on the issue draft (closed rows, "Fatto").
 * Every editor is the control the issue form always used.
 */
export function InvoiceRecordSections({
  display,
  control,
  inline,
  companyEditable,
  collectionsLocked,
  companyId,
  bankAccounts,
  onCompanyPicked,
  schedulePreview,
}: InvoiceRecordSectionsProps) {
  const { t } = useTranslation()
  const bank = display.financialAccount
  const withSchedule = (editor: ReactNode) => (
    <>
      {editor}
      {schedulePreview}
    </>
  )

  return (
    <>
      <RecordSection title={t('invoices.detail.header')} icon={<FileText />}>
        {collectionsLocked ? (
          <p className="text-xs text-muted-foreground">{t('invoices.rebalance.lockedHint')}</p>
        ) : null}
        <RecordFieldList>
          <RecordInlineField
            field="document_date"
            label={t('invoiceEditor.fields.documentDate')}
            inline={inline}
            canEdit={!collectionsLocked}
            editor={withSchedule(<InvoiceDocumentDateField control={control} />)}
          >
            {display.documentDate ? formatDate(display.documentDate) : <DetailEmpty />}
          </RecordInlineField>
          <RecordInlineField
            field="company_id"
            label={t('invoiceEditor.fields.company')}
            inline={inline}
            canEdit={companyEditable}
            editor={
              <InvoiceEditorRelationField
                control={control}
                name="company_id"
                resource={COMPANIES_FOR_SELECT_RESOURCE}
                label={t('invoiceEditor.fields.company')}
                placeholder={t('invoiceEditor.fields.selectCompany')}
                selected={display.company}
                required
                onPicked={onCompanyPicked}
              />
            }
          >
            {refName(display.company)}
          </RecordInlineField>
          <RecordInlineField
            field="customer_registry_id"
            label={t('invoiceEditor.fields.customer')}
            inline={inline}
            canEdit={!collectionsLocked}
            editor={
              <InvoiceEditorRelationField
                control={control}
                name="customer_registry_id"
                resource={REGISTRIES_FOR_SELECT_RESOURCE}
                label={t('invoiceEditor.fields.customer')}
                placeholder={t('invoiceEditor.fields.selectCustomer')}
                selected={display.customer}
                required
              />
            }
          >
            {refName(display.customer)}
          </RecordInlineField>
          <RecordInlineField
            field="payment_method_id"
            label={t('invoiceEditor.fields.paymentMethod')}
            inline={inline}
            canEdit={!collectionsLocked}
            editor={withSchedule(
              <InvoiceEditorRelationField
                control={control}
                name="payment_method_id"
                resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE}
                label={t('invoiceEditor.fields.paymentMethod')}
                placeholder={t('invoiceEditor.fields.selectPaymentMethod')}
                selected={display.paymentMethod}
                required
              />,
            )}
          >
            {refName(display.paymentMethod)}
          </RecordInlineField>
          <RecordInlineField
            field="financial_account_id"
            label={t('invoiceEditor.fields.financialAccount')}
            inline={inline}
            editor={
              <InvoiceEditorBankField control={control} companyId={companyId} bankAccounts={bankAccounts} selected={bank} />
            }
          >
            {bank ? (bank.iban ? `${bank.name} - ${bank.iban}` : bank.name) : t('invoiceEditor.fields.noBank')}
          </RecordInlineField>
          <RecordInlineField
            field="layout_id"
            label={t('invoiceEditor.fields.layout')}
            inline={inline}
            editor={<InvoiceLayoutField control={control} selected={display.layout} />}
          >
            {display.layout?.name ?? t('invoices.detail.layoutDefault')}
          </RecordInlineField>
          <RecordInlineField
            field="tag"
            label={t('invoiceEditor.fields.tag')}
            inline={inline}
            editor={<InvoiceTagField control={control} />}
          >
            {display.tag ? <InvoiceTagBadge tag={display.tag} /> : <DetailEmpty />}
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('invoices.detail.notes')} icon={<StickyNote />}>
        <RecordFieldList>
          <RecordInlineField
            field="notes"
            label={t('invoiceEditor.fields.notes')}
            inline={inline}
            editor={<InvoiceNoteField control={control} name="notes" />}
          >
            {textValue(display.notes)}
          </RecordInlineField>
          <RecordInlineField
            field="internal_note"
            label={t('invoiceEditor.fields.internalNote')}
            inline={inline}
            editor={<InvoiceNoteField control={control} name="internal_note" />}
          >
            {textValue(display.internalNote)}
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>
    </>
  )
}
