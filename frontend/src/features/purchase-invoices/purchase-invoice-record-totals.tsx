import { Wallet } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import type { PurchaseInvoiceRecordEditor } from '@/features/purchase-invoices/use-purchase-invoice-inline-edit'

type DeductionName = 'withholding_amount' | 'enasarco_amount'

interface DeductionRowProps {
  name: DeductionName
  label: string
  value: string
  editor: PurchaseInvoiceRecordEditor
  canEdit: boolean
}

/** One deduction edited in place: empty = 0, a number otherwise (the server freezes it after the first payment). */
function DeductionRow({ name, label, value, editor, canEdit }: DeductionRowProps) {
  const control = editor.form.control
  return (
    <RecordInlineField
      field={name}
      label={label}
      inline={editor.inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name={name} metaKey={name} label={label}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                step="0.01"
                min="0"
                className="h-8 text-right text-sm"
                disabled={disabled}
                readOnly={readOnly}
                {...field}
                value={typeof field.value === 'number' ? field.value : ''}
                onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
              />
            </FormControl>
          )}
        </MetaField>
      }
    >
      <span>{formatEuro(value)}</span>
    </RecordInlineField>
  )
}

interface PurchaseInvoiceRecordTotalsProps {
  invoice: PurchaseInvoice
  editor: PurchaseInvoiceRecordEditor
}

/** "Totali": amounts of the document; the two deductions are editable until the first payment and while not linked to a settlement. */
export function PurchaseInvoiceRecordTotals({ invoice, editor }: PurchaseInvoiceRecordTotalsProps) {
  const { t } = useTranslation()
  const column = (key: string) => t(`purchaseInvoiceViews.columns.${key}`)
  const canEditDeductions =
    !invoice.has_payments && invoice.commission_settlement == null && invoice.permissions.actions.update === true

  return (
    <RecordSection title={t('purchaseInvoiceViews.detail.totals')} icon={<Wallet />}>
      <RecordFieldList>
        <RecordField label={column('net_amount')}>{formatEuro(invoice.net_amount)}</RecordField>
        <RecordField label={column('vat_amount')}>{formatEuro(invoice.vat_amount)}</RecordField>
        <RecordField label={column('total_amount')}>{formatEuro(invoice.total_amount)}</RecordField>
        <DeductionRow
          name="withholding_amount"
          label={column('withholding_amount')}
          value={invoice.withholding_amount}
          editor={editor}
          canEdit={canEditDeductions}
        />
        <DeductionRow
          name="enasarco_amount"
          label={column('enasarco_amount')}
          value={invoice.enasarco_amount}
          editor={editor}
          canEdit={canEditDeductions}
        />
        <RecordField label={column('payable_amount')}>{formatEuro(invoice.payable_amount)}</RecordField>
        <RecordField label={column('paid_amount')}>{formatEuro(invoice.paid_amount)}</RecordField>
        <RecordField label={column('residual_amount')}>{formatEuro(invoice.residual_amount)}</RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}
