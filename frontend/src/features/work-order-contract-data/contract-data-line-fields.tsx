import { useTranslation } from 'react-i18next'
import { Pencil } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { InvoiceTypeBadge, PaymentStatusIndicator } from '@/features/invoices/invoice-status-badges'
import { Amount, PaymentStatusBadge, ProductCell } from '@/features/work-order-contract-data/contract-data-cells'
import { ContractDataLineBox } from '@/features/work-order-contract-data/contract-data-line-box'
import { netAmountHint } from '@/features/work-order-contract-data/contract-data-formulas'
import type { ContractDataLine, ContractLineDocument } from '@/features/work-order-contract-data/types'

/** "Documento": the proforma/invoice billing the line and how far it is paid, as the legacy `controlPayment()`. */
function LineDocument({ document }: { document: ContractLineDocument | null }) {
  const { t } = useTranslation()

  if (document === null) {
    return <span>{t('workOrders.contractData.fields.noDocument')}</span>
  }

  return (
    <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
      <InvoiceTypeBadge type={document.type} />
      <span className="tabular-nums">{document.number_label}</span>
      <PaymentStatusIndicator status={document.payment_status} />
    </span>
  )
}

interface ContractDataLineFieldsProps {
  line: ContractDataLine
  /** `manage_payments`: the pencil on "Stato pagamento" opens the line payment dialog. */
  canManage: boolean
  /** Multi-offer work order: the line names its own client. */
  showRegistry: boolean
  onEdit: () => void
}

/**
 * One product of the commessa as the legacy "Dati contrattuali commessa"
 * (spec 0403): Titolo, Importo, Stato pagamento, Documento, Accordo sui
 * pagamenti, Insoluti. Mounted only for an actor who may see the contract data.
 */
export function ContractDataLineFields({ line, canManage, showRegistry, onEdit }: ContractDataLineFieldsProps) {
  const { t } = useTranslation()
  const label = (key: string) => t(`workOrders.contractData.fields.${key}`)
  const { status, payment_agreement: agreement, has_unpaid: hasUnpaid } = line.payment

  return (
    <ContractDataLineBox label={`${line.product.code} ${line.product.name}`}>
      <RecordFieldList>
        <RecordField label={label('title')}>
          <ProductCell line={line} showRegistry={showRegistry} />
        </RecordField>
        <RecordField label={label('amount')}>
          <Amount value={line.net_amount} hint={netAmountHint(line, t)} className="font-semibold tabular-nums" />
        </RecordField>
        <RecordField label={label('paymentStatus')}>
          <span className="inline-flex max-w-full min-w-0 items-center gap-1">
            <PaymentStatusBadge status={status} />
            {canManage ? (
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6 shrink-0 text-muted-foreground"
                aria-label={t('workOrders.contractData.payment.edit', { product: line.product.name })}
                onClick={onEdit}
              >
                <Pencil className="size-3.5" aria-hidden="true" />
              </Button>
            ) : null}
          </span>
        </RecordField>
        <RecordField label={label('document')}>
          <LineDocument document={line.document} />
        </RecordField>
        <RecordField label={label('agreement')}>
          {agreement ? <span className="whitespace-pre-line">{agreement}</span> : <DetailEmpty />}
        </RecordField>
        <RecordField label={label('unpaid')}>{hasUnpaid ? t('common.yes') : t('common.no')}</RecordField>
      </RecordFieldList>
    </ContractDataLineBox>
  )
}
