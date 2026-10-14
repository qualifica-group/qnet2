import type { ReactNode } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FileText, NotebookText, Tags } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import { BANK_ACCOUNT_FOR_SELECT_PARAMS, FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import { INVOICE_TAGS_FOR_SELECT_RESOURCE } from '@/features/invoice-tags/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro, parseNumberInput } from '@/features/invoices/invoice-format'
import type { InvoiceRecordFormValues } from '@/features/invoices/invoice-schema'
import { InvoiceTagBadge } from '@/features/invoices/invoice-status-badges'
import type { InvoiceWithPermissions } from '@/features/invoices/types'
import type { InvoiceRecordEditor } from '@/features/invoices/use-invoice-inline-edit'

const LAYOUT_PARAMS = { module: 'invoices' } as const

type RecordControl = Control<InvoiceRecordFormValues>
type RelationName = 'customer_registry_id' | 'payment_method_id' | 'financial_account_id' | 'invoice_tag_id' | 'layout_id'
type TextName = 'document_date' | 'external_number' | 'external_date' | 'notes' | 'internal_note'

interface SectionProps {
  invoice: InvoiceWithPermissions
  editor: InvoiceRecordEditor
}

function TextValue({ value }: { value: string | null | undefined }) {
  return value && value.trim() !== '' ? <span className="break-words whitespace-pre-wrap">{value}</span> : <DetailEmpty />
}

interface TextEditorProps {
  control: RecordControl
  name: TextName
  label: string
  type?: 'text' | 'date'
  multiline?: boolean
  required?: boolean
}

/** A text/date/long-text control of the record, authorization-aware (`MetaField`). */
function TextEditor({ control, name, label, type = 'text', multiline = false, required }: TextEditorProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label} required={required}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          {multiline ? (
            <Textarea className="min-h-16 text-sm" disabled={disabled} readOnly={readOnly} {...field} value={field.value ?? ''} />
          ) : (
            <Input type={type} className="h-8 text-sm" disabled={disabled} readOnly={readOnly} {...field} value={field.value ?? ''} />
          )}
        </FormControl>
      )}
    </MetaField>
  )
}

interface RelationEditorProps {
  control: RecordControl
  name: RelationName
  label: string
  resource: string
  selected: RelationFieldRef | null
  params?: Record<string, string | number>
  required?: boolean
  placeholder: string
}

/** A server-backed picker of the record (`RelationSelectField`, authorization-aware). */
function RelationEditor(props: RelationEditorProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      {...props}
      metaKey={props.name}
      searchPlaceholder={t('invoiceEditor.fields.searchPlaceholder')}
      emptyLabel={t('invoiceEditor.fields.selectEmpty')}
      errorLabel={t('invoiceEditor.fields.selectError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

function Editors({ children }: { children: ReactNode }) {
  return <div className="flex min-w-0 flex-col gap-3">{children}</div>
}

/**
 * "Testata": the proforma header. Collected installments freeze date,
 * customer and payment method (spec 0196 D-1); a registered invoice locks the
 * whole header through the server's field matrix.
 */
function HeaderSection({ invoice, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form
  const unfrozen = !invoice.has_collections

  return (
    <RecordSection title={t('invoices.detail.header')} icon={<FileText />}>
      <RecordFieldList>
        <RecordInlineField
          field="document_date"
          label={t('invoices.columns.document_date')}
          inline={inline}
          canEdit={unfrozen}
          editor={<TextEditor control={control} name="document_date" type="date" label={t('invoices.columns.document_date')} required />}
        >
          <span>{formatDate(invoice.document_date)}</span>
        </RecordInlineField>
        <RecordField label={t('invoices.columns.company')}>
          <span>{invoice.company.name}</span>
        </RecordField>
        <RecordInlineField
          field="customer_registry_id"
          label={t('invoiceEditor.fields.customer')}
          inline={inline}
          canEdit={unfrozen}
          editor={<RelationEditor control={control} name="customer_registry_id" label={t('invoiceEditor.fields.customer')} resource={REGISTRIES_FOR_SELECT_RESOURCE} selected={invoice.customer} placeholder={t('invoiceEditor.fields.selectCustomer')} required />}
        >
          <span>{invoice.customer.name}</span>
        </RecordInlineField>
        <RecordInlineField
          field="payment_method_id"
          label={t('invoices.columns.payment_method')}
          inline={inline}
          canEdit={unfrozen}
          editor={<RelationEditor control={control} name="payment_method_id" label={t('invoices.columns.payment_method')} resource={PAYMENT_METHODS_FOR_SELECT_RESOURCE} selected={invoice.payment_method} placeholder={t('invoiceEditor.fields.selectPaymentMethod')} required />}
        >
          <span>{invoice.payment_method.name}</span>
        </RecordInlineField>
        <RecordInlineField
          field="financial_account_id"
          label={t('invoiceEditor.fields.financialAccount')}
          inline={inline}
          editor={
            <RelationEditor
              control={control}
              name="financial_account_id"
              label={t('invoiceEditor.fields.financialAccount')}
              resource={FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE}
              selected={invoice.financial_account}
              params={{ ...BANK_ACCOUNT_FOR_SELECT_PARAMS, company_id: invoice.company.id }}
              placeholder={t('invoiceEditor.fields.noBank')}
            />
          }
        >
          <TextValue value={invoice.financial_account?.name} />
        </RecordInlineField>
        <RecordField label={t('invoices.columns.work_order_code')}>
          <TextValue value={invoice.work_order?.code} />
        </RecordField>
        <RecordField label={t('invoices.columns.quote_code')}>
          <TextValue value={invoice.quote?.code} />
        </RecordField>
        <RecordField label={t('invoices.detail.createdBy')}>
          <span>{invoice.created_by.name}</span>
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Registrazione e classificazione": what stays editable after registration (spec 0194 D-14, spec 0243). */
function RegistrationSection({ invoice, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor
  const { control } = form
  const externalReference = invoice.external_number ? `${invoice.external_number} - ${formatDate(invoice.external_date)}` : null

  return (
    <RecordSection title={t('invoices.record.registration')} icon={<Tags />}>
      <RecordFieldList>
        <RecordInlineField
          field="external_number"
          label={t('invoices.detail.externalReference')}
          inline={inline}
          editor={
            <Editors>
              <TextEditor control={control} name="external_number" label={t('invoices.details.externalNumber')} />
              <TextEditor control={control} name="external_date" type="date" label={t('invoices.details.externalDate')} />
              <p className="text-xs text-muted-foreground">{t('invoices.details.externalNumberHint')}</p>
            </Editors>
          }
        >
          <TextValue value={externalReference} />
        </RecordInlineField>
        <RecordInlineField
          field="invoice_tag_id"
          label={t('invoiceEditor.fields.tag')}
          inline={inline}
          editor={<RelationEditor control={control} name="invoice_tag_id" label={t('invoiceEditor.fields.tag')} resource={INVOICE_TAGS_FOR_SELECT_RESOURCE} selected={invoice.tag} placeholder={t('invoiceEditor.fields.noTag')} />}
        >
          {invoice.tag ? <InvoiceTagBadge tag={invoice.tag} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="deviation"
          label={t('invoices.details.deviation')}
          inline={inline}
          editor={
            <MetaField control={control} name="deviation" metaKey="deviation" label={t('invoices.details.deviation')}>
              {({ field, disabled }) => (
                <FormControl>
                  <Input
                    type="number"
                    step="0.01"
                    inputMode="decimal"
                    className="h-8 text-sm"
                    disabled={disabled}
                    name={field.name}
                    ref={field.ref}
                    onBlur={field.onBlur}
                    value={field.value ?? ''}
                    onChange={(event) => field.onChange(parseNumberInput(event.target.value, null))}
                  />
                </FormControl>
              )}
            </MetaField>
          }
        >
          {invoice.deviation === null ? <DetailEmpty /> : <span>{formatEuro(invoice.deviation)}</span>}
        </RecordInlineField>
        <RecordInlineField
          field="layout_id"
          label={t('invoices.detail.layout')}
          inline={inline}
          editor={<RelationEditor control={control} name="layout_id" label={t('invoices.detail.layout')} resource={DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE} selected={invoice.layout} params={LAYOUT_PARAMS} placeholder={t('invoices.detail.layoutDefault')} />}
        >
          <span>{invoice.layout?.name ?? t('invoices.detail.layoutDefault')}</span>
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Note": the printed notes (proforma header) and the internal note (details, always editable). */
function NotesSection({ invoice, editor }: SectionProps) {
  const { t } = useTranslation()
  const { form, inline } = editor

  return (
    <RecordSection title={t('invoices.detail.notes')} icon={<NotebookText />} full>
      <RecordFieldList>
        <RecordInlineField
          field="notes"
          label={t('invoices.detail.notes')}
          inline={inline}
          editor={<TextEditor control={form.control} name="notes" label={t('invoices.detail.notes')} multiline />}
        >
          <TextValue value={invoice.notes} />
        </RecordInlineField>
        <RecordInlineField
          field="internal_note"
          label={t('invoices.detail.internalNote')}
          inline={inline}
          editor={<TextEditor control={form.control} name="internal_note" label={t('invoices.detail.internalNote')} multiline />}
        >
          <TextValue value={invoice.internal_note} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The invoice record's editable sections (spec 0261 D-3). */
export function InvoiceRecordSections(props: SectionProps) {
  return (
    <>
      <HeaderSection {...props} />
      <RegistrationSection {...props} />
      <NotesSection {...props} />
    </>
  )
}
